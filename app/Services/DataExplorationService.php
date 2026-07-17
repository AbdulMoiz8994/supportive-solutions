<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DataExplorationExportLog;
use App\Models\DataExplorationView;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExplorationService
{
    public function __construct(
        protected VisitReportService $visitReports,
    ) {}

    public function pageData(?int $orgId, Request $request, User $user): array
    {
        $this->syncAgentSuggestedViews($orgId, $user);

        $dataset = $request->input('dataset', 'visits');
        $config = $this->buildConfig($request);
        $result = $this->query($orgId, $dataset, $config, $user);

        return [
            'title' => 'Data Exploration 2.0',
            'datasets' => $this->datasetOptions(),
            'dataset' => $dataset,
            'config' => $config,
            'columns' => $result['columns'],
            'rows' => $result['rows'],
            'chart' => $result['chart'],
            'truncated' => $result['truncated'] ?? false,
            'totalMatched' => $result['total_matched'] ?? count($result['rows']),
            'groupByOptions' => $this->groupByOptions($dataset),
            'aggregateOptions' => $this->aggregateOptions($dataset),
            'filterFields' => $this->filterFields($dataset),
            'statusOptions' => $this->statusOptions($dataset),
            'datePresets' => $this->datePresets(),
            'clients' => $this->clientOptions($orgId),
            'caregivers' => $this->caregiverOptions($orgId),
            'chartTypes' => config('data_exploration.chart_types', []),
            'savedViews' => DataExplorationView::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('user_id', $user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'dataset', 'config', 'schedule_frequency'])
                ->map(fn (DataExplorationView $v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'dataset' => $v->dataset,
                    'config' => $v->config,
                    'schedule_frequency' => $v->schedule_frequency,
                ])
                ->all(),
            'csrfToken' => csrf_token(),
        ];
    }

    /**
     * Document Agent capability `suggest_exploration_views`: ensure rolling
     * weekly/monthly saved views exist for the user when that agent is active
     * and the capability is not monitor-only. Re-syncs date windows on each
     * page load so schedules stay current.
     *
     * @return list<DataExplorationView>
     */
    public function syncAgentSuggestedViews(?int $orgId, User $user): array
    {
        $orgId = $orgId ?? $user->organization_id;
        $createdOrUpdated = [];

        $agent = $orgId
            ? app(AiAgentRegistryService::class)->findBySlug($orgId, 'document')
            : null;

        if (! $agent || ! $agent->canRunAction('suggest_exploration_views')) {
            return [];
        }

        // Only auto-create when capability mode is auto (queue/monitor skip).
        if ($agent->actionMode('suggest_exploration_views') !== 'auto') {
            return [];
        }

        $suggestions = [
            [
                'name' => '[Agent] Visits this week',
                'dataset' => 'visits',
                'config' => [
                    'date_from' => now()->startOfWeek()->toDateString(),
                    'date_to' => now()->endOfWeek()->toDateString(),
                    'group_by' => null,
                    'aggregate' => 'count',
                    'chart_type' => 'bar',
                    'suggested_by_agent' => $agent->slug,
                    'suggested_by_agent_id' => $agent->id,
                ],
                'schedule_frequency' => 'weekly',
            ],
            [
                'name' => '[Agent] Billable hours by caregiver',
                'dataset' => 'visits',
                'config' => [
                    'date_from' => now()->startOfMonth()->toDateString(),
                    'date_to' => now()->endOfMonth()->toDateString(),
                    'status' => 'complete',
                    'group_by' => 'employee_id',
                    'aggregate' => 'sum_hours',
                    'chart_type' => 'bar',
                    'suggested_by_agent' => $agent->slug,
                    'suggested_by_agent_id' => $agent->id,
                ],
                'schedule_frequency' => 'weekly',
            ],
        ];

        foreach ($suggestions as $suggestion) {
            $existing = DataExplorationView::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('user_id', $user->id)
                ->where('name', $suggestion['name'])
                ->first();

            if ($existing) {
                $existing->update([
                    'config' => array_merge(
                        is_array($existing->config) ? $existing->config : [],
                        $suggestion['config'],
                    ),
                    'schedule_frequency' => $suggestion['schedule_frequency'],
                ]);
                $createdOrUpdated[] = $existing->fresh();

                continue;
            }

            $createdOrUpdated[] = $this->saveView(
                $orgId,
                $user,
                $suggestion['name'],
                $suggestion['dataset'],
                $suggestion['config'],
                $suggestion['schedule_frequency'],
            );
        }

        return $createdOrUpdated;
    }

    public function query(?int $orgId, string $dataset, array $config, ?User $user = null): array
    {
        $definition = config("data_exploration.datasets.{$dataset}");
        $meta = [
            'group_by_options' => $this->groupByOptions($dataset),
            'aggregate_options' => $this->aggregateOptions($dataset),
            'filter_fields' => $this->filterFields($dataset),
            'status_options' => $this->statusOptions($dataset),
            'date_presets' => $this->datePresets(),
        ];

        if (! $definition) {
            return ['columns' => [], 'rows' => [], 'chart' => ['labels' => [], 'values' => []], 'truncated' => false, 'total_matched' => 0, ...$meta];
        }

        $config = $this->normalizeConfig($config);

        $groupBy = $config['group_by'] ?? null;
        $aggregate = $config['aggregate'] ?? 'count';
        // Aggregations need the full filtered population (same idea as Visit Reports counters).
        $config['_fetch_mode'] = $groupBy ? 'aggregate' : 'list';

        $rows = match ($dataset) {
            'clients' => $this->queryClients($orgId, $config),
            'caregivers' => $this->queryCaregivers($orgId, $config),
            'visits' => $this->queryVisits($orgId, $config),
            'authorizations' => $this->queryAuthorizations($orgId, $config),
            'documents' => $this->queryDocuments($orgId, $config),
            'billing' => $this->queryBilling($orgId, $config),
            default => collect(),
        };

        if ($this->shouldMaskPhi($user)) {
            $rows = $rows->map(fn (array $row) => $this->maskPhiRow($row))->values();
        }

        if ($groupBy) {
            return [
                ...$this->aggregateRows($rows, $groupBy, $aggregate, $dataset),
                'truncated' => false,
                'total_matched' => $rows->count(),
                ...$meta,
            ];
        }

        $totalMatched = $rows->count();
        $displayRows = $rows->take(500)->values();

        return [
            'columns' => array_values($definition['columns']),
            'rows' => $displayRows->all(),
            'chart' => $this->buildChartFromRows($displayRows, $config['chart_type'] ?? 'bar'),
            'truncated' => $totalMatched >= 500,
            'total_matched' => $totalMatched,
            ...$meta,
        ];
    }

    /**
     * Apply date presets and coerce empty filter values.
     */
    public function normalizeConfig(array $config): array
    {
        $preset = $config['date_preset'] ?? null;
        if ($preset && $preset !== 'custom') {
            [$from, $to] = $this->resolveDateRange($preset, null, null);
            $config['date_from'] = $from;
            $config['date_to'] = $to;
        }

        foreach (['status', 'county', 'program', 'group_by', 'employee_id', 'client_id'] as $key) {
            if (($config[$key] ?? null) === '' || ($config[$key] ?? null) === 0) {
                $config[$key] = null;
            }
        }

        return $config;
    }

    public function saveView(
        ?int $orgId,
        User $user,
        string $name,
        string $dataset,
        array $config,
        ?string $scheduleFrequency = null,
    ): DataExplorationView {
        return DataExplorationView::create([
            'organization_id' => $orgId ?? $user->organization_id,
            'user_id' => $user->id,
            'name' => $name,
            'dataset' => $dataset,
            'config' => $config,
            'schedule_frequency' => $scheduleFrequency,
        ]);
    }

    public function deleteView(?int $orgId, User $user, int $viewId): bool
    {
        $view = DataExplorationView::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('user_id', $user->id)
            ->whereKey($viewId)
            ->first();

        if (! $view) {
            return false;
        }

        return (bool) $view->delete();
    }

    public function exportCsv(?int $orgId, string $dataset, array $config, ?User $user = null): array
    {
        $result = $this->query($orgId, $dataset, $config, $user);
        $headers = collect($result['columns'])->pluck('label')->all();
        $rows = collect($result['rows'])->map(function (array $row) use ($result) {
            return collect($result['columns'])->map(function (array $col) use ($row) {
                $key = $col['label'] ?? '';

                return $row[$key] ?? $row[array_key_first($row)] ?? '';
            })->all();
        });

        return [$headers, $rows->all()];
    }

    public function logExport(?int $orgId, User $user, string $dataset, string $format, int $rowCount, array $config): void
    {
        DataExplorationExportLog::create([
            'organization_id' => $orgId ?? $user->organization_id,
            'user_id' => $user->id,
            'dataset' => $dataset,
            'format' => $format,
            'row_count' => $rowCount,
            'config' => $config,
        ]);
    }

    public function exportXlsx(?int $orgId, string $dataset, array $config, ?User $user = null): StreamedResponse
    {
        [$headers, $rows] = $this->exportCsv($orgId, $dataset, $config, $user);
        $filename = 'data-exploration-'.$dataset.'-'.now()->format('Y-m-d').'.xlsx';
        $title = config("data_exploration.datasets.{$dataset}.label", 'Data Exploration');

        return response()->streamDownload(function () use ($headers, $rows, $title) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(substr($title, 0, 31));
            $sheet->setCellValue('A1', $title);

            $rowNum = 3;
            foreach ($headers as $col => $header) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1).$rowNum, $header);
            }
            $rowNum++;

            foreach ($rows as $dataRow) {
                foreach (array_values($dataRow) as $col => $value) {
                    $sheet->setCellValue(
                        Coordinate::stringFromColumnIndex($col + 1).$rowNum,
                        is_scalar($value) ? $value : json_encode($value)
                    );
                }
                $rowNum++;
            }

            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportPdf(?int $orgId, string $dataset, array $config, ?User $user = null): Response
    {
        [$headers, $rows] = $this->exportCsv($orgId, $dataset, $config, $user);
        $filename = 'data-exploration-'.$dataset.'-'.now()->format('Y-m-d').'.pdf';
        $title = config("data_exploration.datasets.{$dataset}.label", 'Data Exploration');
        $periodLabel = trim(($config['date_from'] ?? '').' – '.($config['date_to'] ?? ''), ' –');

        $pdf = Pdf::loadView('pages.data-exploration.export-pdf', [
            'title' => $title,
            'periodLabel' => $periodLabel !== '' ? $periodLabel : now()->toDateString(),
            'headers' => $headers,
            'rows' => $rows,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    private function buildConfig(Request $request): array
    {
        $preset = $request->input('date_preset');
        [$from, $to] = $this->resolveDateRange(
            $preset,
            $request->input('date_from'),
            $request->input('date_to'),
        );

        return [
            'date_preset' => $preset ?: 'custom',
            'date_from' => $from,
            'date_to' => $to,
            'status' => $request->input('status'),
            'county' => $request->input('county'),
            'program' => $request->input('program'),
            'employee_id' => $request->integer('employee_id') ?: null,
            'client_id' => $request->integer('client_id') ?: null,
            'group_by' => $request->input('group_by'),
            'aggregate' => $request->input('aggregate', 'count'),
            'chart_type' => $request->input('chart_type', 'bar'),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(?string $preset, ?string $from, ?string $to): array
    {
        return match ($preset) {
            'this_week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'last_week' => [now()->subWeek()->startOfWeek()->toDateString(), now()->subWeek()->endOfWeek()->toDateString()],
            'this_month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'last_30_days' => [now()->subDays(30)->toDateString(), today()->toDateString()],
            default => [
                $from ?: now()->subMonth()->toDateString(),
                $to ?: today()->toDateString(),
            ],
        };
    }

    /**
     * Align list vs aggregate fetch sizes with Visit Reports (list ≤500, counters ≤5000).
     */
    private function fetchLimit(array $config, bool $statusNeedsPostFilter = false): int
    {
        if (($config['_fetch_mode'] ?? 'list') === 'aggregate') {
            return 5000;
        }

        return $statusNeedsPostFilter ? 2500 : 500;
    }

    private function queryClients(?int $orgId, array $config): Collection
    {
        return Client::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when($config['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($config['county'] ?? null, fn ($q, $c) => $q->where('county', $c))
            ->when($config['program'] ?? null, function ($q, $program) {
                $q->where(function ($inner) use ($program) {
                    $inner->where('mco_name', 'like', "%{$program}%")
                        ->orWhere('coverage_type_id', 'like', "%{$program}%");
                });
            })
            ->when(
                ($config['date_from'] ?? null) || ($config['date_to'] ?? null),
                function ($q) use ($config) {
                    if ($config['date_from'] ?? null) {
                        $q->whereDate('created_at', '>=', $config['date_from']);
                    }
                    if ($config['date_to'] ?? null) {
                        $q->whereDate('created_at', '<=', $config['date_to']);
                    }
                }
            )
            ->limit($this->fetchLimit($config))
            ->get()
            ->map(fn (Client $c) => [
                'Client' => trim($c->first_name.' '.$c->last_name),
                'Status' => $c->status ?? '—',
                'County' => $c->county ?? '—',
                'Program' => $c->mco_name ?? '—',
                '_group_county' => $c->county ?: 'No county',
                '_group_status' => $c->status ?? 'No status',
            ]);
    }

    private function queryCaregivers(?int $orgId, array $config): Collection
    {
        return Employee::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when($config['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when(
                ($config['date_from'] ?? null) || ($config['date_to'] ?? null),
                function ($q) use ($config) {
                    if ($config['date_from'] ?? null) {
                        $q->whereDate('created_at', '>=', $config['date_from']);
                    }
                    if ($config['date_to'] ?? null) {
                        $q->whereDate('created_at', '<=', $config['date_to']);
                    }
                }
            )
            ->limit($this->fetchLimit($config))
            ->get()
            ->map(fn (Employee $e) => [
                'Caregiver' => trim($e->first_name.' '.$e->last_name),
                'Status' => $e->status ?? '—',
                'Position' => $e->position ?? '—',
                '_group_status' => $e->status ?? 'No status',
                '_group_position' => $e->position ?: 'No position',
            ]);
    }

    private function queryVisits(?int $orgId, array $config): Collection
    {
        $statusFilter = $config['status'] ?? null;
        $isAggregate = ($config['_fetch_mode'] ?? 'list') === 'aggregate';

        $schedules = Schedule::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('event_type', Schedule::EVENT_CARE_VISIT)
            ->with(['client', 'employee'])
            ->filterDateRange($config['date_from'] ?? null, $config['date_to'] ?? null)
            ->when($config['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($config['client_id'] ?? null, fn ($q, $id) => $q->where('client_id', $id))
            ->when($config['program'] ?? null, function ($q, $program) {
                $q->whereHas('client', fn ($c) => $c->where('mco_name', 'like', "%{$program}%"));
            })
            ->orderByDesc(DB::raw('COALESCE(start_at, date)'))
            ->limit($this->fetchLimit($config, (bool) $statusFilter))
            ->get();

        if ($statusFilter) {
            $schedules = $schedules->filter(
                fn (Schedule $s) => $this->visitMatchesStatusFilter($s, (string) $statusFilter)
            )->values();

            if (! $isAggregate) {
                $schedules = $schedules->take(500);
            }
        }

        return $schedules->map(fn (Schedule $s) => [
            'Caregiver' => $s->employee ? trim($s->employee->first_name.' '.$s->employee->last_name) : '—',
            'Client' => $s->client ? trim($s->client->first_name.' '.$s->client->last_name) : '—',
            'Date' => $s->date?->format('Y-m-d') ?? '—',
            'Status' => $this->visitReports->resolveReportStatus($s),
            'Hours' => (float) ($s->total_hours ?? 0),
            '_group_caregiver' => $s->employee ? trim($s->employee->first_name.' '.$s->employee->last_name) : 'Unassigned caregiver',
            '_group_client_id' => $s->client ? trim($s->client->first_name.' '.$s->client->last_name) : 'No client',
            '_group_status' => $this->visitReports->resolveReportStatus($s),
        ]);
    }

    /**
     * Match Visit Reports filter values (complete, needs_review, …) or raw schedule statuses.
     */
    private function visitMatchesStatusFilter(Schedule $schedule, string $filter): bool
    {
        $reportStatus = $this->visitReports->resolveReportStatus($schedule);

        if ($reportStatus === $filter || strcasecmp($reportStatus, $filter) === 0) {
            return true;
        }

        $normalizedFilter = Schedule::normalizeStatus($filter);
        $normalizedStatus = Schedule::normalizeStatus((string) $schedule->status);

        return $normalizedStatus === $normalizedFilter
            || strcasecmp((string) $schedule->status, $filter) === 0;
    }

    private function shouldMaskPhi(?User $user): bool
    {
        return $user !== null && ! $user->isAdmin();
    }

    private function maskPhiRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            $label = strtolower(ltrim((string) $key, '_'));
            $looksLikeSensitiveColumn = str_contains($label, 'email')
                || str_contains($label, 'phone')
                || str_contains($label, 'ssn')
                || str_contains($label, 'social')
                || str_contains($label, 'client')
                || str_contains($label, 'caregiver')
                || str_contains($label, 'name')
                || str_contains($label, 'group_caregiver')
                || str_contains($label, 'group_client')
                || $label === 'document'
                || $label === 'group';

            if ($looksLikeSensitiveColumn || $this->valueLooksLikePhi($value)) {
                $row[$key] = $this->maskMiddle($value);
            }
        }

        return $row;
    }

    private function valueLooksLikePhi(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) === 9 || strlen($digits) === 10 || strlen($digits) === 11) {
            // Phone-like or SSN-like digit runs (ignore plain short numbers elsewhere).
            if (preg_match('/^\d{3}-?\d{2}-?\d{4}$/', preg_replace('/[^\d-]/', '', $value) ?? '')) {
                return true;
            }
            if (preg_match('/^\+?1?[\s\-.]?\(?\d{3}\)?[\s\-.]?\d{3}[\s\-.]?\d{4}$/', $value)) {
                return true;
            }
        }

        return false;
    }

    private function maskMiddle(string $value): string
    {
        $length = mb_strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        $keep = max(1, (int) floor($length / 4));

        return mb_substr($value, 0, $keep).'***'.mb_substr($value, -$keep);
    }

    private function queryAuthorizations(?int $orgId, array $config): Collection
    {
        return \App\Models\CareDetail::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->with('client')
            ->when($config['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($config['date_from'] ?? null, fn ($q, $d) => $q->whereDate('end_date', '>=', $d))
            ->when($config['date_to'] ?? null, fn ($q, $d) => $q->whereDate('end_date', '<=', $d))
            ->limit($this->fetchLimit($config))
            ->get()
            ->map(fn ($a) => [
                'Client' => $a->client ? trim($a->client->first_name.' '.$a->client->last_name) : '—',
                'Code' => $a->billing_code ?? '—',
                'Status' => $a->status ?? '—',
                'Units' => (int) ($a->total_units ?? 0),
                'Expires' => $a->end_date?->format('Y-m-d') ?? '—',
                '_group_status' => $a->status ?? 'No status',
                '_group_billing_code' => $a->billing_code ?: 'No code',
            ]);
    }

    private function queryDocuments(?int $orgId, array $config): Collection
    {
        return Document::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when($config['status'] ?? null, fn ($q, $s) => $q->where('verification_status', $s))
            ->when($config['date_from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($config['date_to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->limit($this->fetchLimit($config))
            ->get()
            ->map(fn (Document $d) => [
                'Document' => $d->name,
                'Category' => $d->category ?? '—',
                'Status' => $d->verification_status ?? '—',
                'Uploaded' => $d->created_at?->format('Y-m-d') ?? '—',
                '_group_category' => $d->category ?: 'Other',
                '_group_verification_status' => $d->verification_status ?? 'No status',
            ]);
    }

    private function queryBilling(?int $orgId, array $config): Collection
    {
        return \App\Models\BillingClaimAudit::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->with('client')
            ->when($config['status'] ?? null, fn ($q, $s) => $q->where('claim_status', $s))
            ->when($config['date_from'] ?? null, fn ($q, $d) => $q->whereDate('period_start', '>=', $d))
            ->when($config['date_to'] ?? null, fn ($q, $d) => $q->whereDate('period_end', '<=', $d))
            ->when($config['program'] ?? null, fn ($q, $p) => $q->where('program_type', 'like', "%{$p}%"))
            ->limit($this->fetchLimit($config))
            ->get()
            ->map(fn ($b) => [
                'Client' => $b->client ? trim($b->client->first_name.' '.$b->client->last_name) : '—',
                'Claim' => $b->claim_number ?? '—',
                'Status' => $b->claim_status ?? '—',
                'Billed' => number_format((float) ($b->total_amount ?? 0), 2),
                'Paid' => number_format((float) ($b->paid_amount ?? 0), 2),
                '_group_claim_status' => $b->claim_status ?? 'No status',
                '_group_program' => $b->program_type ?: 'No program',
                '_group_status' => $b->claim_status ?? 'No status',
                '_billed_raw' => (float) ($b->total_amount ?? 0),
            ]);
    }

    private function aggregateRows(Collection $rows, string $groupBy, string $aggregate, string $dataset): array
    {
        $groupKey = match ($groupBy) {
            'caregiver', 'employee_id' => '_group_caregiver',
            'client_id' => '_group_client_id',
            'claim_status' => '_group_claim_status',
            'status' => '_group_status',
            'category' => '_group_category',
            'billing_code' => '_group_billing_code',
            'verification_status' => '_group_verification_status',
            'program' => '_group_program',
            default => '_group_'.$groupBy,
        };

        $grouped = $rows->groupBy(fn ($row) => $row[$groupKey] ?? 'Unspecified');

        $aggregated = $grouped->map(function (Collection $group, string $label) use ($aggregate) {
            $value = match ($aggregate) {
                'sum_hours' => round($group->sum('Hours'), 2),
                'sum_units' => $group->sum('Units'),
                'sum_billed' => round($group->sum('_billed_raw'), 2),
                'sum_paid' => $group->sum(fn ($r) => (float) str_replace(',', '', $r['Paid'] ?? 0)),
                default => $group->count(),
            };

            return [
                'Group' => $label,
                'Total' => $value,
            ];
        })->sortByDesc('Total')->values();

        return [
            'columns' => [
                ['label' => 'Group'],
                ['label' => 'Total'],
            ],
            'rows' => $aggregated->all(),
            'chart' => [
                'labels' => $aggregated->pluck('Group')->all(),
                'values' => $aggregated->pluck('Total')->all(),
            ],
        ];
    }

    private function buildChartFromRows(Collection $rows, string $chartType): array
    {
        if ($chartType === 'table' || $rows->isEmpty()) {
            return ['labels' => [], 'values' => []];
        }

        $grouped = $rows->groupBy(fn ($row) => array_values($row)[0] ?? 'Unknown')->map->count();

        return [
            'labels' => $grouped->keys()->take(12)->all(),
            'values' => $grouped->values()->take(12)->all(),
        ];
    }

    private function datasetOptions(): array
    {
        return collect(config('data_exploration.datasets', []))
            ->map(fn ($def, $key) => ['value' => $key, 'label' => $def['label']])
            ->values()
            ->all();
    }

    private function groupByOptions(string $dataset): array
    {
        $groups = config("data_exploration.datasets.{$dataset}.group_by", []);

        return collect($groups)->map(fn ($g) => [
            'value' => $g,
            'label' => $this->groupByLabel($g),
        ])->all();
    }

    private function aggregateOptions(string $dataset): array
    {
        $aggregates = config("data_exploration.datasets.{$dataset}.aggregates", ['count']);

        return collect($aggregates)->map(fn ($a) => [
            'value' => $a,
            'label' => $this->aggregateLabel($a),
        ])->all();
    }

    private function groupByLabel(string $group): string
    {
        return match ($group) {
            'employee_id' => 'Caregiver',
            'client_id' => 'Client',
            'billing_code' => 'Billing code',
            'claim_status' => 'Claim status',
            'verification_status' => 'Verification status',
            default => ucfirst(str_replace('_', ' ', $group)),
        };
    }

    private function aggregateLabel(string $aggregate): string
    {
        return match ($aggregate) {
            'count' => 'Count',
            'sum_hours' => 'Sum of hours',
            'sum_units' => 'Sum of units',
            'sum_billed' => 'Sum billed',
            'sum_paid' => 'Sum paid',
            default => ucfirst(str_replace('_', ' ', $aggregate)),
        };
    }

    private function filterFields(string $dataset): array
    {
        return config("data_exploration.datasets.{$dataset}.filters", []);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(string $dataset): array
    {
        return match ($dataset) {
            'visits' => [
                ['value' => VisitReportService::STATUS_SCHEDULED, 'label' => 'Scheduled'],
                ['value' => VisitReportService::STATUS_IN_PROGRESS, 'label' => 'In progress'],
                ['value' => VisitReportService::STATUS_COMPLETE, 'label' => 'Complete'],
                ['value' => VisitReportService::STATUS_NEEDS_REVIEW, 'label' => 'Needs review'],
                ['value' => VisitReportService::STATUS_MISSED, 'label' => 'Missed'],
            ],
            'clients', 'caregivers' => [
                ['value' => 'Active', 'label' => 'Active'],
                ['value' => 'Inactive', 'label' => 'Inactive'],
                ['value' => 'Pending', 'label' => 'Pending'],
            ],
            'documents' => [
                ['value' => 'verified', 'label' => 'Verified'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
            'authorizations' => [
                ['value' => 'Active', 'label' => 'Active'],
                ['value' => 'Expired', 'label' => 'Expired'],
                ['value' => 'Pending', 'label' => 'Pending'],
            ],
            'billing' => [
                ['value' => 'paid', 'label' => 'Paid'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'denied', 'label' => 'Denied'],
                ['value' => 'submitted', 'label' => 'Submitted'],
            ],
            default => [],
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function datePresets(): array
    {
        return [
            ['value' => 'this_week', 'label' => 'This week'],
            ['value' => 'last_week', 'label' => 'Last week'],
            ['value' => 'this_month', 'label' => 'This month'],
            ['value' => 'last_month', 'label' => 'Last month'],
            ['value' => 'last_30_days', 'label' => 'Last 30 days'],
            ['value' => 'custom', 'label' => 'Custom range'],
        ];
    }

    private function clientOptions(?int $orgId): array
    {
        return Client::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Client $c) => [
                'id' => $c->id,
                'name' => trim($c->first_name.' '.$c->last_name),
            ])
            ->all();
    }

    private function caregiverOptions(?int $orgId): array
    {
        return Employee::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => trim($e->first_name.' '.$e->last_name),
            ])
            ->all();
    }
}
