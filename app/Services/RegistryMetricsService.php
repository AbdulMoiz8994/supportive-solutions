<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ComplianceForm;
use App\Models\Employee;
use App\Support\AgencyScope;
use App\Support\CaregiverRegistryMetrics;
use App\Support\CaregiverStatus;
use App\Support\ClientRegistryStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RegistryMetricsService
{
    public function __construct(
        protected ApprovalQueueMetricsService $queueMetrics,
    ) {}

    public function organizationId(): ?int
    {
        return AgencyScope::organizationId();
    }

    /**
     * @return Collection<int, Client>
     */
    public function clients(): Collection
    {
        return Client::query()
            ->tap(fn ($q) => AgencyScope::applyOrganization($q))
            ->with(['coverageType', 'statusRecord', 'careDetails', 'employees', 'contacts', 'caregiverAssignments'])
            ->latest()
            ->get();
    }

    /**
     * @return array{total:int,active:int,on_hold:int,discharged:int,auth_expiring:int,auth_expired:int,dhs:int,mich:int}
     */
    public function clientStats(?Collection $clients = null): array
    {
        $clients ??= $this->clients();

        $rows = $clients->map(function (Client $client) {
            $statusName = $client->statusRecord?->name ?? $client->status ?? 'Active';
            $statusKey = ClientRegistryStatus::normalize($statusName);

            return [
                'status' => $statusKey,
                'program' => $client->program_label,
            ];
        });

        return [
            'total' => $clients->count(),
            'active' => $rows->where('status', 'active')->count(),
            'on_hold' => $rows->where('status', 'on_hold')->count(),
            'discharged' => $rows->where('status', 'discharged')->count(),
            'auth_expiring' => $clients->filter(function (Client $client) {
                $days = $client->authStatus()['days'];

                return $days !== null && $days >= 0 && $days <= 21;
            })->count(),
            // Only genuinely expired prior-auths (red). DHS Time/Task reassessments are
            // never "expired", so they must not inflate this count.
            'auth_expired' => $clients->filter(fn (Client $client) => $client->authStatus()['tone'] === 'red')->count(),
            'dhs' => $rows->filter(fn ($r) => ($r['program'] ?? null) === 'DHS' && $r['status'] === 'active')->count(),
            'mich' => $rows->filter(fn ($r) => ($r['program'] ?? null) === 'MICH' && $r['status'] === 'active')->count(),
        ];
    }

    /**
     * @param  iterable<int, array{status_key?: string, status?: string, program?: string}>  $rows
     * @return array<string, int>
     */
    public function clientTabCounts(iterable $rows): array
    {
        return ClientRegistryStatus::tabCounts($rows);
    }

    public function activeClientCount(): int
    {
        return $this->clientStats()['active'];
    }

    /**
     * @return Collection<int, Employee>
     */
    public function caregivers(): Collection
    {
        return Employee::query()
            ->tap(fn ($q) => AgencyScope::applyOrganization($q))
            ->with(['assignments.client', 'backgroundChecks', 'complianceForms'])
            ->where('position', 'Caregiver')
            ->latest()
            ->get();
    }

    /**
     * @return array{total:int,active:int,pending:int,on_hold:int,on_leave:int,inactive:int,checks_expiring:int,compliance_missing:int,family_pct:int}
     */
    public function caregiverStats(?Collection $caregivers = null): array
    {
        $caregivers ??= $this->caregivers();

        $normalized = $caregivers->map(fn (Employee $c) => CaregiverStatus::normalize($c));

        return [
            'total' => $caregivers->count(),
            'active' => $normalized->filter(fn ($status) => $status === 'Active')->count(),
            'pending' => $normalized->filter(fn ($status) => $status === 'Pending')->count(),
            'on_hold' => $normalized->filter(fn ($status) => $status === 'On Hold')->count(),
            'on_leave' => $normalized->filter(fn ($status) => $status === 'On Leave')->count(),
            'inactive' => $normalized->filter(fn ($status) => $status === 'Inactive')->count(),
            'checks_expiring' => $caregivers->filter(fn (Employee $c) => CaregiverRegistryMetrics::rowFlags($c)['checks_expiring'])->count(),
            'checks_flagged' => $caregivers->filter(fn (Employee $c) => CaregiverRegistryMetrics::rowFlags($c)['checks_flagged'])->count(),
            'compliance_missing' => $caregivers->filter(fn (Employee $c) => CaregiverRegistryMetrics::rowFlags($c)['compliance_missing'])->count(),
            'family_pct' => $caregivers->count()
                ? (int) round($caregivers->where('caregiver_type', 'Family')->count() / $caregivers->count() * 100)
                : 0,
        ];
    }

    public function activeCaregiverCount(): int
    {
        return $this->caregiverStats()['active'];
    }

    /**
     * Single source of truth for the monthly compliance-form cycle: how many
     * clients have this month's compliance form in. Used by both the
     * Compliance page tracker and the Reports "Monthly forms received" KPI so
     * the two pages can never disagree (client review item A5).
     *
     * @return array{total:int,received:int,received_pct:int,received_client_ids:Collection<int,int>}
     */
    public function complianceFormStats(?int $organizationId, ?Carbon $period = null): array
    {
        $period ??= now();

        try {
            $receivedClientIds = ComplianceForm::query()
                ->whereNotNull('submitted_at')
                ->whereMonth('submitted_at', $period->month)
                ->whereYear('submitted_at', $period->year)
                ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
                ->pluck('client_id')
                ->filter()
                ->unique()
                ->values();
        } catch (\Throwable) {
            $receivedClientIds = collect();
        }

        $total = Client::withoutGlobalScopes()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->count();

        $received = $receivedClientIds->count();

        return [
            'total' => $total,
            'received' => $received,
            'received_pct' => $total ? (int) round($received / $total * 100) : 0,
            'received_client_ids' => $receivedClientIds,
        ];
    }

    /**
     * Billing & Claims Audit metrics for the current month (aligned with registry header + tabs).
     * Claim-audit rows are merged with unmatched client invoices so dashboard money KPIs
     * stay in sync with billing holds on the approval queue.
     *
     * @return array{in_flight:int,outstanding_amount:float,billed_amount:float,collected_amount:float}
     */
    public function billingClaimStats(?int $organizationId, ?Carbon $period = null): array
    {
        return $this->queueMetrics->periodBillingDollarStats(
            $organizationId,
            $period ?? now()->startOfMonth()
        );
    }
}
