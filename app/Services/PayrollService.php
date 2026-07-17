<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\PayRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PayrollService
{
    public function __construct(
        protected PayrollBatchService $batchService,
        protected PayrollCalculationService $calculationService,
        protected PayrollGraceWindowService $graceWindowService,
        protected PayrollEligibilityService $eligibilityService,
        protected PayrollHoursResolver $hoursResolver,
        protected PayrollRecordWorkflowService $recordWorkflow,
        protected PayrollAuditService $auditService,
        protected PayrollDocumentService $documentService
    ) {}

    public function baseQuery(?int $organizationId = null): Builder
    {
        $query = PayRecord::query()->with(['employee', 'client', 'complianceForm', 'batch']);

        if ($organizationId !== null) {
            $query->where('pay_records.organization_id', $organizationId);
        }

        return $query;
    }

    public function parsePeriod(?string $period): Carbon
    {
        if ($period && preg_match('/^\d{4}-\d{2}$/', $period)) {
            return Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    public function periodLabel(Carbon $period): string
    {
        return $period->format('F Y');
    }

    public function periodKey(Carbon $period): string
    {
        return $period->format('Y-m');
    }

    public function adjacentPeriod(Carbon $period, int $direction): Carbon
    {
        return $period->copy()->addMonths($direction)->startOfMonth();
    }

    public function periodOptions(Carbon $current): array
    {
        $options = [];

        for ($i = 0; $i < 3; $i++) {
            $p = $current->copy()->subMonths($i);
            $options[] = [
                'value' => $p->format('Y-m'),
                'label' => $p->format('M Y'),
            ];
        }

        return $options;
    }

    public function normalizeFilters(array $filters): array
    {
        return [
            'period'         => $filters['period'] ?? now()->format('Y-m'),
            'search'         => isset($filters['search']) ? trim((string) $filters['search']) : null,
            'client_search'  => isset($filters['client_search']) ? trim((string) $filters['client_search']) : null,
            'status'         => $filters['status'] ?? null,
            'caregiver_type' => $filters['caregiver_type'] ?? null,
            'live_in'        => ! empty($filters['live_in']) ? true : null,
            'evv_exempt'     => ! empty($filters['evv_exempt']) ? true : null,
            'in_grace'       => ! empty($filters['in_grace']) ? true : null,
            'held'           => ! empty($filters['held']) ? true : null,
        ];
    }

    public function refreshRecord(PayRecord $record, ?Carbon $asOf = null): PayRecord
    {
        $this->recordWorkflow->refreshRecord($record, $asOf);
        $record->saveQuietly();

        return $record->fresh(['employee', 'client', 'complianceForm', 'batch']);
    }

    public function filteredQuery(?int $organizationId, array $filters = []): Builder
    {
        $filters = $this->normalizeFilters($filters);
        $periodKey = $this->periodKey($this->parsePeriod($filters['period']));

        $query = $this->baseQuery($organizationId)->forPeriod($periodKey);

        if ($filters['search']) {
            $search = $filters['search'];
            $query->whereHas('employee', function (Builder $q) use ($search) {
                $q->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%');
            });
        }

        if ($filters['client_search']) {
            $clientSearch = $filters['client_search'];
            $query->whereHas('client', function (Builder $q) use ($clientSearch) {
                $q->where('first_name', 'like', '%'.$clientSearch.'%')
                    ->orWhere('last_name', 'like', '%'.$clientSearch.'%');
            });
        }

        if ($filters['status']) {
            $tabMap = PayRecord::tabStatuses();
            $status = $tabMap[$filters['status']] ?? PayRecord::mapLegacyStatus($filters['status']);
            $query->where('status', $status);
        }

        if ($filters['caregiver_type']) {
            $query->where('caregiver_type', $filters['caregiver_type']);
        }

        if ($filters['live_in']) {
            $query->whereHas('employee', fn (Builder $q) => $q->where('live_in', true));
        }

        if ($filters['evv_exempt']) {
            $query->whereHas('employee', fn (Builder $q) => $q->where('evv_exempt', true));
        }

        if ($filters['in_grace']) {
            $query->inGrace();
        }

        if ($filters['held']) {
            $query->held();
        }

        return $query->orderBy('status')->orderBy('employee_id');
    }

    public function paginate(?int $organizationId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $records = $this->filteredQuery($organizationId, $filters)->paginate($perPage);

        $records->getCollection()->transform(function (PayRecord $record) {
            return $this->recordWorkflow->refreshRecord($record);
        });

        return $records->withQueryString();
    }

    public function recordsForPeriod(?int $organizationId, Carbon $period): Collection
    {
        return $this->baseQuery($organizationId)
            ->forPeriod($this->periodKey($period))
            ->get()
            ->map(fn (PayRecord $record) => $this->recordWorkflow->refreshRecord($record));
    }

    public function summaryForPeriod(?int $organizationId, Carbon $period): array
    {
        $records = $this->recordsForPeriod($organizationId, $period);
        $dates = $this->batchService->batchDatesForPeriod($period);

        return [
            'gross_amount'       => $records->filter(fn (PayRecord $r) => $r->gross !== null)->sum('gross'),
            'caregiver_count'    => $records->count(),
            'ready_count'        => $records->where('status', PayRecord::STATUS_READY)->count(),
            'in_grace_count'     => $records->where('status', PayRecord::STATUS_IN_GRACE)->count(),
            'late_rolled_count'  => $records->where('status', PayRecord::STATUS_LATE_ROLLED)->count(),
            'held_count'         => $records->where('status', PayRecord::STATUS_HELD)->count(),
            'paid_count'         => $records->where('status', PayRecord::STATUS_PAID)->count(),
            'build_date'         => $dates['build_date'],
            'pay_date'           => $dates['pay_date'],
            'build_date_label'   => $dates['build_date']->format('D M j'),
            'pay_date_label'     => $dates['pay_date']->format('D M j'),
        ];
    }

    public function tabCounts(?int $organizationId, Carbon $period): array
    {
        $records = $this->recordsForPeriod($organizationId, $period);

        return [
            'all'         => $records->count(),
            'ready'       => $records->where('status', PayRecord::STATUS_READY)->count(),
            'in_grace'    => $records->where('status', PayRecord::STATUS_IN_GRACE)->count(),
            'late_rolled' => $records->where('status', PayRecord::STATUS_LATE_ROLLED)->count(),
            'held'        => $records->where('status', PayRecord::STATUS_HELD)->count(),
            'paid'        => $records->where('status', PayRecord::STATUS_PAID)->count(),
        ];
    }

    public function getIndexData(?int $organizationId, array $filters): array
    {
        $period = $this->parsePeriod($filters['period'] ?? null);

        return [
            'period'        => $period,
            'summary'       => $this->summaryForPeriod($organizationId, $period),
            'tabCounts'     => $this->tabCounts($organizationId, $period),
            'periodOptions' => $this->periodOptions($period),
            'subtitle'      => $this->buildSubtitle($organizationId, $period),
        ];
    }

    public function buildSubtitle(?int $organizationId, Carbon $period): string
    {
        $summary = $this->summaryForPeriod($organizationId, $period);

        return sprintf(
            '%s cycle · batch builds %s → pays %s · %d ready · %d in grace · %d held',
            $period->format('M Y'),
            $summary['build_date_label'],
            $summary['pay_date_label'],
            $summary['ready_count'],
            $summary['in_grace_count'],
            $summary['held_count']
        );
    }

    public function getShowData(PayRecord $record): array
    {
        $this->recordWorkflow->refreshRecord($record);
        $record->loadMissing(['employee.assignments', 'client', 'complianceForm', 'batch', 'auditLogs', 'latestPayrollClaim']);

        $employee = $record->employee;
        $form = $record->complianceForm ?? $this->hoursResolver->findComplianceForm($record);
        $eligibleFrom = $employee ? $this->eligibilityService->resolveEligibleFrom($employee) : null;
        $batchDates = $record->period_key
            ? $this->batchService->batchDatesForPeriod(Carbon::createFromFormat('Y-m', $record->period_key)->startOfMonth())
            : [];

        return [
            'record'            => $record,
            'periodLabel'       => $record->period ?: $this->periodLabel($this->parsePeriod($record->period_key)),
            'caregiverType'     => config('payroll.caregiver_types.'.$this->eligibilityService->resolveCaregiverType($record), 'Caregiver'),
            'caregiverTypeKey'  => $this->eligibilityService->resolveCaregiverType($record),
            'liveInLabel'       => $employee?->live_in ? 'EVV-exempt (live-in)' : 'EVV required',
            'hoursSource'       => $record->hours_source ?? '—',
            'eligibleFrom'      => $eligibleFrom,
            'caseStart'         => $employee ? $this->eligibilityService->resolveCaseStart($employee) : null,
            'champsDate'        => $employee ? $this->eligibilityService->champsAssociationDate($employee) : null,
            'graceDaysRemaining'=> $form?->submitted_at ? $this->graceWindowService->daysRemaining($form->submitted_at) : null,
            'graceEndDate'      => $record->grace_end_date,
            'complianceForm'    => $form,
            'lifecycle'         => $this->recordWorkflow->buildLifecycleTimeline($record),
            'batchDates'        => $batchDates,
            'documents'         => $this->buildDocuments($record, $form),
            'auditLogs'         => $record->auditLogs->sortByDesc('occurred_at')->take(10),
            'stubAvailable'     => $this->documentService->stubIsAvailable($record),
            'headerSubtitle'    => $this->buildDetailSubtitle($record, $employee),
            'batchScheduleLabel'=> isset($batchDates['build_date'], $batchDates['pay_date'])
                ? 'Scheduled · '.$batchDates['build_date']->format('D').'→'.$batchDates['pay_date']->format('D')
                : null,
            'payrollClaim'      => $record->latestPayrollClaim,
        ];
    }

    protected function buildDetailSubtitle(PayRecord $record, $employee): string
    {
        $parts = array_filter([
            config('payroll.caregiver_types.'.$this->eligibilityService->resolveCaregiverType($record)),
            $employee?->live_in ? 'live-in (EVV-exempt)' : null,
            $record->client ? 'client '.$record->client->first_name.' '.$record->client->last_name.($record->program_tag ? ' ('.$record->program_tag.')' : '') : null,
        ]);

        return implode(' · ', $parts);
    }

    protected function buildDocuments(PayRecord $record, $form): array
    {
        $month = explode(' ', (string) $record->period)[0] ?? 'current';
        $stubAvailable = $this->documentService->stubIsAvailable($record);
        $docs = [];

        $docs[] = [
            'label'     => "Pay stub ({$month})",
            'type'      => 'stub',
            'available' => $stubAvailable,
            'route'     => $stubAvailable ? route('payroll.stub', $record) : null,
            'status'    => $record->isPaid() ? 'Available' : 'on payout',
        ];

        if ($form) {
            $docs[] = [
                'label'     => 'Compliance form ('.($form->period_label ?? $record->period).')',
                'type'      => 'compliance',
                'available' => false,
                'status'    => 'On file',
            ];
        }

        $docs[] = [
            'label'     => 'W-4 / employment file',
            'type'      => 'w4',
            'available' => false,
            'status'    => 'On file',
        ];

        return $docs;
    }

    public function updateWage(PayRecord $record, float $wage, User $actor): PayRecord
    {
        if ($record->isImmutable()) {
            throw new \RuntimeException('Paid or locked records cannot be edited.');
        }

        $before = (string) $record->rate;
        $employee = $record->employee;
        $employee->hourly_wage = round($wage, 2);
        $employee->saveQuietly();

        $this->calculationService->applyCalculation($record, $record->hours, $wage);
        $record->saveQuietly();

        $this->auditService->logWageUpdate($record, $actor, $before, (string) $record->rate);

        return $record->fresh(['employee', 'client', 'complianceForm']);
    }

    public function applyHold(PayRecord $record, string $reason, User $actor): PayRecord
    {
        if ($record->isImmutable()) {
            throw new \RuntimeException('Paid or locked records cannot be held.');
        }

        $record->hold_reason = strip_tags(trim($reason));
        $this->recordWorkflow->refreshRecord($record);
        $record->status = PayRecord::STATUS_HELD;
        $record->saveQuietly();

        $this->auditService->logHoldApplied($record, $actor, $record->hold_reason);

        return $record->fresh(['employee', 'client', 'complianceForm']);
    }

    public function releaseHold(PayRecord $record, User $actor, ?string $note = null): PayRecord
    {
        if (! $record->hold_reason) {
            return $record;
        }

        $before = $record->hold_reason;
        $record->hold_reason = null;
        $this->recordWorkflow->refreshRecord($record);
        $record->saveQuietly();

        $this->auditService->logHoldRelease($record, $actor, $before, $note);

        return $record->fresh(['employee', 'client', 'complianceForm']);
    }

    public function exportRows(?int $organizationId, array $filters): Collection
    {
        return $this->filteredQuery($organizationId, $filters)
            ->get()
            ->map(fn (PayRecord $record) => $this->recordWorkflow->refreshRecord($record));
    }

    public function organizationsForPeriod(Carbon $period): Collection
    {
        $orgIds = PayRecord::withoutGlobalScopes()
            ->forPeriod($this->periodKey($period))
            ->distinct()
            ->pluck('organization_id')
            ->filter();

        return Organization::query()->whereIn('id', $orgIds)->orderBy('name')->get();
    }

    public function resolveBatchOrganizationId(User $user, Carbon $period, ?int $requestedOrgId = null): int
    {
        if (! $user->isSuperAdmin() && $user->organization_id) {
            return (int) $user->organization_id;
        }

        if ($requestedOrgId && Organization::query()->whereKey($requestedOrgId)->exists()) {
            return $requestedOrgId;
        }

        $orgs = $this->organizationsForPeriod($period);

        if ($orgs->count() === 1) {
            return (int) $orgs->first()->id;
        }

        if ($orgs->isEmpty()) {
            throw new \InvalidArgumentException('No payroll records exist for this period — nothing to batch.');
        }

        throw new \InvalidArgumentException('Select an organization to build a payroll batch.');
    }
}
