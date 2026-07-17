<?php

namespace App\Services\Billing;

use App\Models\BillingClaimAudit;
use App\Models\Client;
use App\Models\Schedule;
use App\Services\VisitReportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Scans each billing cycle for clients with clean visit hours and a valid
 * authorization — the same criteria the monthly automation uses before
 * generating claims.
 */
class BillingEligibilityScanService
{
    public function __construct(
        protected VisitReportService $visitReports,
    ) {}

    /**
     * @return Collection<int, array{client: Client, payable_hours: float}>
     */
    public function scanEligibleClients(?int $organizationId, Carbon $period): Collection
    {
        $periodStart = $period->copy()->startOfMonth();
        $periodEnd = $period->copy()->endOfMonth();

        $clientIds = Schedule::withoutGlobalScopes()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereIn('status', [Schedule::STATUS_COMPLETED, 'Verified', 'completed'])
            ->distinct()
            ->pluck('client_id');

        $eligible = collect();

        foreach ($clientIds as $clientId) {
            $client = Client::withoutGlobalScopes()
                ->with(['coverageType', 'employees', 'careDetails'])
                ->find($clientId);

            if (! $client || ($organizationId && (int) $client->organization_id !== (int) $organizationId)) {
                continue;
            }

            $hours = $this->payableHoursForClient($client, $periodStart, $periodEnd);

            if ($hours <= 0 || ! $this->clientHasValidAuthForPeriod($client, $periodStart, $periodEnd)) {
                continue;
            }

            $eligible->push([
                'client' => $client,
                'payable_hours' => $hours,
            ]);
        }

        return $eligible->values();
    }

    public function eligibleCount(?int $organizationId, Carbon $period): int
    {
        return $this->scanEligibleClients($organizationId, $period)->count();
    }

    public function isClientEligible(Client $client, Carbon $period): bool
    {
        $periodStart = $period->copy()->startOfMonth();
        $periodEnd = $period->copy()->endOfMonth();

        if ($this->payableHoursForClient($client, $periodStart, $periodEnd) <= 0) {
            return false;
        }

        return $this->clientHasValidAuthForPeriod($client, $periodStart, $periodEnd);
    }

    public function clientHasValidAuthForPeriod(Client $client, Carbon $periodStart, Carbon $periodEnd): bool
    {
        $auth = $client->currentAuthorization();

        if (! $auth) {
            return false;
        }

        if ($auth->start_date && $auth->start_date->copy()->startOfDay()->gt($periodEnd)) {
            return false;
        }

        // DHS Time/Task: reassessment dates never block billing eligibility.
        if ($client->program_label === 'DHS') {
            return true;
        }

        if (! $auth->end_date) {
            return false;
        }

        // Authorization must cover at least part of the billed service month.
        if ($auth->end_date->copy()->startOfDay()->lt($periodStart)) {
            return false;
        }

        return true;
    }

    protected function payableHoursForClient(Client $client, Carbon $periodStart, Carbon $periodEnd): float
    {
        return $this->visitReports->payableHours(
            $client->organization_id,
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
            $client->id,
        );
    }
}
