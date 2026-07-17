<?php

namespace App\Services\Reports;

use App\Models\BillingClaimAudit;
use App\Models\Client;
use App\Models\CustomReportDefinition;
use App\Models\Employee;
use App\Models\User;
use App\Services\BillingClaimsAuditService;
use App\Services\PayrollService;
use App\Support\ReportPresenter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CustomReportBuilderService
{
    public function __construct(
        protected BillingClaimsAuditService $billingService,
        protected PayrollService $payrollService,
    ) {}

    /**
     * @return array{source?: string, columns?: list<string>, filters?: array<string, mixed>, group_by?: string, schedule_frequency?: string}
     */
    public function parsePrompt(string $prompt): array
    {
        $filters = [];
        $columns = ['Client', 'Program', 'County'];
        $source = 'clients';
        $groupBy = null;

        $lower = strtolower($prompt);

        if (preg_match('/\bdhs\b/i', $prompt)) {
            $filters['program'] = 'dhs';
        } elseif (preg_match('/\bmich\b/i', $prompt)) {
            $filters['program'] = 'mich';
        }

        if (preg_match('/\b(\w+)\s+county\b/i', $prompt, $m)) {
            $filters['county'] = ucwords(strtolower($m[1]));
        }

        if (preg_match('/hours?\s+(?:dropped?|declined?|decreased?|delta|Δ)\s+(?:more\s+than|over|>\s*)?(\d+)\s*%/i', $prompt, $m)) {
            $filters['hours_delta_max'] = -1 * (int) $m[1];
        } elseif (preg_match('/hours?\s+(?:increased?|grew|up)\s+(?:more\s+than|over|>\s*)?(\d+)\s*%/i', $prompt, $m)) {
            $filters['hours_delta_min'] = (int) $m[1];
        } elseif (preg_match('/hours?\s*[ΔΔ]\s*<\s*[-−]?(\d+)\s*%/i', $prompt, $m)) {
            $filters['hours_delta_max'] = -1 * (int) $m[1];
        }

        if (str_contains($lower, 'caregiver') || str_contains($lower, 'employee')) {
            $source = 'caregivers';
            $columns = ['Caregiver', 'Type', 'County', 'Hours'];
        } elseif (str_contains($lower, 'billing') || str_contains($lower, 'claim') || str_contains($lower, 'billed')) {
            $source = 'billing';
            $columns = ['Client', 'Program', 'Billed', 'Collected', 'Status'];
        } elseif (str_contains($lower, 'compliance') || str_contains($lower, 'form') || str_contains($lower, 'background')) {
            $source = 'compliance';
            $columns = ['Caregiver', 'Forms', 'Background', 'Status'];
        }

        if (str_contains($lower, 'asw')) {
            $columns[] = 'ASW';
            $groupBy = 'ASW';
        }

        if (str_contains($lower, 'hours') && ! in_array('Hours Δ', $columns, true)) {
            $columns[] = 'Hours Δ';
        }

        if (preg_match('/group(?:ed)?\s+by\s+(\w+)/i', $prompt, $m)) {
            $groupBy = ucfirst(strtolower($m[1]));
        }

        return array_filter([
            'source' => $source,
            'columns' => array_values(array_unique($columns)),
            'filters' => $filters ?: null,
            'group_by' => $groupBy,
            'schedule_frequency' => str_contains($lower, 'weekly') ? 'weekly' : (str_contains($lower, 'monthly') ? 'monthly' : null),
            'prompt' => $prompt,
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  array{source?: string, columns?: list<string>, filters?: array<string, mixed>, group_by?: string}  $config
     * @return array{kpis: list<array<string, string>>, sections: list<array<string, mixed>>, preview: list<array<string, string>>}
     */
    public function buildPreview(?int $orgId, array $config): array
    {
        $period = Carbon::now()->startOfMonth();
        $source = $config['source'] ?? 'clients';
        $columns = $config['columns'] ?? ['Client', 'Program', 'County'];
        $filters = $config['filters'] ?? [];

        $rows = $this->fetchRows($orgId, $source, $period, $filters);
        $preview = $rows->take(25)->map(fn (array $row) => $this->projectRow($row, $columns))->values()->all();

        $grouped = $this->groupRows($preview, $config['group_by'] ?? null, $columns);

        return [
            'kpis' => [
                ['label' => 'Matching rows', 'value' => (string) $rows->count(), 'sub' => $source.' source'],
                ['label' => 'Preview shown', 'value' => (string) count($preview), 'sub' => 'of '.count($columns).' columns'],
                ['label' => 'Program filter', 'value' => strtoupper($filters['program'] ?? 'All'), 'sub' => 'applied'],
                ['label' => 'County filter', 'value' => $filters['county'] ?? 'All', 'sub' => 'applied'],
            ],
            'sections' => [
                [
                    'title' => 'Preview',
                    'subtitle' => $period->format('M Y'),
                    'headers' => $columns,
                    'rows' => array_map(fn (array $row) => array_values($row), $grouped),
                ],
            ],
            'preview' => $preview,
        ];
    }

    /**
     * @param  array{name: string, source?: string, columns?: list<string>, filters?: array<string, mixed>, group_by?: string, schedule_frequency?: string, schedule_recipients?: list<string>, prompt?: string}  $payload
     */
    public function save(User $user, ?int $orgId, array $payload): CustomReportDefinition
    {
        return CustomReportDefinition::create([
            'organization_id' => $orgId,
            'user_id' => $user->id,
            'name' => $payload['name'],
            'slug' => CustomReportDefinition::uniqueSlug($payload['name']),
            'source' => $payload['source'] ?? 'clients',
            'columns' => $payload['columns'] ?? [],
            'filters' => $payload['filters'] ?? [],
            'group_by' => $payload['group_by'] ?? null,
            'schedule_frequency' => $payload['schedule_frequency'] ?? null,
            'schedule_recipients' => $payload['schedule_recipients'] ?? null,
            'prompt' => $payload['prompt'] ?? null,
        ]);
    }

    /**
     * @return array{kpis: list<array<string, string>>, sections: list<array<string, mixed>>, preview: list<array<string, string>>}
     */
    public function runSaved(CustomReportDefinition $def, ?int $orgId): array
    {
        return $this->buildPreview($orgId, [
            'source' => $def->source,
            'columns' => $def->columns ?? [],
            'filters' => $def->filters ?? [],
            'group_by' => $def->group_by,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function fetchRows(?int $orgId, string $source, Carbon $period, array $filters): Collection
    {
        return match ($source) {
            'caregivers' => $this->caregiverRows($orgId, $period, $filters),
            'billing' => $this->billingRows($orgId, $period, $filters),
            'compliance' => $this->complianceRows($orgId, $period, $filters),
            default => $this->clientRows($orgId, $period, $filters),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function clientRows(?int $orgId, Carbon $period, array $filters): Collection
    {
        $clients = Client::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('status', 'Active')
            ->with('coverageType')
            ->get();

        if (! empty($filters['program'])) {
            $label = strtoupper($filters['program']) === 'DHS' ? 'DHS' : 'MICH';
            $clients = $clients->filter(fn (Client $c) => $c->program_label === $label);
        }

        if (! empty($filters['county'])) {
            $clients = $clients->filter(fn (Client $c) => strcasecmp((string) $c->county, $filters['county']) === 0);
        }

        $hoursMap = $this->clientHoursDelta($orgId, $period);

        $rows = $clients->map(function (Client $c) use ($hoursMap, $orgId, $period) {
            $delta = $hoursMap[$c->id] ?? null;

            return [
                'client' => trim($c->first_name.' '.$c->last_name),
                'program' => $c->program_label,
                'county' => $c->county ?? '—',
                'hours' => $delta['current'] ?? 0,
                'hours_delta' => $delta['pct'] ?? null,
                'asw' => $this->clientAsw($c, $orgId, $period),
                'status' => $c->status ?? 'Active',
            ];
        });

        return $this->applyHoursDeltaFilter($rows, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function caregiverRows(?int $orgId, Carbon $period, array $filters): Collection
    {
        $caregivers = Employee::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('position', 'Caregiver')
            ->where(fn ($q) => $q->where('status', 'Active')->orWhereNull('status'))
            ->get();

        if (! empty($filters['county'])) {
            $caregivers = $caregivers->filter(fn (Employee $e) => strcasecmp((string) $e->county, $filters['county']) === 0);
        }

        $hoursMap = $this->caregiverHoursDelta($orgId, $period);

        $rows = $caregivers->map(function (Employee $e) use ($hoursMap) {
            $delta = $hoursMap[$e->id] ?? null;

            return [
                'caregiver' => trim($e->first_name.' '.$e->last_name),
                'type' => $e->caregiver_type ?? '—',
                'county' => $e->county ?? '—',
                'hours' => $delta['current'] ?? 0,
                'hours_delta' => $delta['pct'] ?? null,
                'forms' => ($e->compliance_form_current ?? true) ? 'Current' : 'Late',
                'background' => $e->has_background_check ? 'Clear' : 'Pending',
                'status' => $e->status ?? 'Active',
            ];
        });

        return $this->applyHoursDeltaFilter($rows, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function billingRows(?int $orgId, Carbon $period, array $filters): Collection
    {
        $claims = $this->billingService->applyPeriodScope(
            $this->billingService->baseQuery($orgId),
            $period
        )->with('client')->get();

        if (! empty($filters['program'])) {
            $prog = strtoupper($filters['program']) === 'DHS' ? BillingClaimAudit::PROGRAM_DHS : BillingClaimAudit::PROGRAM_MICH;
            $claims = $claims->where('program_type', $prog);
        }

        return $claims->map(function (BillingClaimAudit $c) {
            $collected = (float) ($c->paid_amount ?? 0);

            return [
                'client' => $c->client ? trim($c->client->first_name.' '.$c->client->last_name) : '—',
                'program' => $c->program_type ?? '—',
                'county' => $c->client?->county ?? '—',
                'billed' => ReportPresenter::money((float) $c->total_amount, true),
                'collected' => ReportPresenter::money($collected, true),
                'status' => $c->claim_status ?? $c->billing_status ?? '—',
                'asw' => $c->authorizing_worker_name ?? '—',
            ];
        })->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function complianceRows(?int $orgId, Carbon $period, array $filters): Collection
    {
        $caregivers = Employee::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('position', 'Caregiver')
            ->get();

        if (! empty($filters['county'])) {
            $caregivers = $caregivers->filter(fn (Employee $e) => strcasecmp((string) $e->county, $filters['county']) === 0);
        }

        return $caregivers->map(fn (Employee $e) => [
            'caregiver' => trim($e->first_name.' '.$e->last_name),
            'forms' => ($e->compliance_form_current ?? true) ? 'On time' : 'Late',
            'background' => $e->has_background_check ? 'Clear' : 'Pending',
            'status' => $e->onboarding_status ?? ($e->status ?? '—'),
            'county' => $e->county ?? '—',
        ])->values();
    }

    /**
     * @return array<int, array{current: float, prior: float, pct: ?float}>
     */
    protected function clientHoursDelta(?int $orgId, Carbon $period): array
    {
        $current = $this->payrollService->recordsForPeriod($orgId, $period);
        $prior = $this->payrollService->recordsForPeriod($orgId, $period->copy()->subMonth());

        $currentByClient = $current->groupBy('client_id')->map(fn (Collection $g) => (float) $g->sum('hours'));
        $priorByClient = $prior->groupBy('client_id')->map(fn (Collection $g) => (float) $g->sum('hours'));

        $ids = $currentByClient->keys()->merge($priorByClient->keys())->unique();

        return $ids->mapWithKeys(function ($clientId) use ($currentByClient, $priorByClient) {
            $cur = (float) ($currentByClient[$clientId] ?? 0);
            $prev = (float) ($priorByClient[$clientId] ?? 0);
            $pct = $prev > 0 ? round((($cur - $prev) / $prev) * 100, 1) : null;

            return [$clientId => ['current' => $cur, 'prior' => $prev, 'pct' => $pct]];
        })->all();
    }

    /**
     * @return array<int, array{current: float, prior: float, pct: ?float}>
     */
    protected function caregiverHoursDelta(?int $orgId, Carbon $period): array
    {
        $current = $this->payrollService->recordsForPeriod($orgId, $period);
        $prior = $this->payrollService->recordsForPeriod($orgId, $period->copy()->subMonth());

        $currentByEmp = $current->groupBy('employee_id')->map(fn (Collection $g) => (float) $g->sum('hours'));
        $priorByEmp = $prior->groupBy('employee_id')->map(fn (Collection $g) => (float) $g->sum('hours'));

        $ids = $currentByEmp->keys()->merge($priorByEmp->keys())->unique();

        return $ids->mapWithKeys(function ($empId) use ($currentByEmp, $priorByEmp) {
            $cur = (float) ($currentByEmp[$empId] ?? 0);
            $prev = (float) ($priorByEmp[$empId] ?? 0);
            $pct = $prev > 0 ? round((($cur - $prev) / $prev) * 100, 1) : null;

            return [$empId => ['current' => $cur, 'prior' => $prev, 'pct' => $pct]];
        })->all();
    }

    protected function clientAsw(Client $client, ?int $orgId, Carbon $period): string
    {
        $claim = $this->billingService->applyPeriodScope(
            $this->billingService->baseQuery($orgId)->where('client_id', $client->id),
            $period
        )->latest('id')->first();

        if ($claim?->authorizing_worker_name) {
            return $claim->authorizing_worker_name;
        }

        $auth = $client->careDetails()->latest('end_date')->first();

        return $auth?->authorized_by ?? '—';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function applyHoursDeltaFilter(Collection $rows, array $filters): Collection
    {
        if (! isset($filters['hours_delta_min']) && ! isset($filters['hours_delta_max'])) {
            return $rows->values();
        }

        return $rows->filter(function (array $row) use ($filters) {
            $pct = $row['hours_delta'] ?? null;
            if ($pct === null) {
                return false;
            }
            if (isset($filters['hours_delta_min']) && $pct < $filters['hours_delta_min']) {
                return false;
            }
            if (isset($filters['hours_delta_max']) && $pct > $filters['hours_delta_max']) {
                return false;
            }

            return true;
        })->values();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    protected function projectRow(array $row, array $columns): array
    {
        $map = [
            'Client' => $row['client'] ?? '—',
            'Caregiver' => $row['caregiver'] ?? ($row['client'] ?? '—'),
            'Program' => $row['program'] ?? '—',
            'County' => $row['county'] ?? '—',
            'Hours' => isset($row['hours']) ? ReportPresenter::number((float) $row['hours'], 1) : '—',
            'Hours Δ' => isset($row['hours_delta']) ? (($row['hours_delta'] >= 0 ? '+' : '').$row['hours_delta'].'%') : '—',
            'ASW' => $row['asw'] ?? '—',
            'Billed' => $row['billed'] ?? '—',
            'Collected' => $row['collected'] ?? '—',
            'Status' => $row['status'] ?? '—',
            'Type' => $row['type'] ?? '—',
            'Forms' => $row['forms'] ?? '—',
            'Background' => $row['background'] ?? '—',
        ];

        $out = [];
        foreach ($columns as $col) {
            $out[$col] = (string) ($map[$col] ?? '—');
        }

        return $out;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  list<string>  $columns
     * @return list<array<string, string>>
     */
    protected function groupRows(array $rows, ?string $groupBy, array $columns): array
    {
        if (! $groupBy || ! in_array($groupBy, $columns, true)) {
            return $rows;
        }

        $grouped = collect($rows)->groupBy($groupBy)->sortKeys();

        return $grouped->flatMap(function (Collection $group, string $key) use ($groupBy) {
            $header = [[$groupBy => '—', ...array_fill_keys(array_diff(array_keys($group->first() ?? []), [$groupBy]), '—')]];
            $header[0][$groupBy] = "— {$key} ({$group->count()}) —";

            return $group->prepend($header[0])->all();
        })->values()->all();
    }
}
