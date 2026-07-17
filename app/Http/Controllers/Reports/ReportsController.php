<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ScheduleReportRequest;
use App\Http\Requests\Reports\StoreCustomReportRequest;
use App\Models\CustomReportDefinition;
use App\Models\ReportSchedule;
use App\Services\Reports\CustomReportBuilderService;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportRunService;
use App\Services\Reports\ReportScheduleService;
use App\Services\Reports\ReportsDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(
        protected ReportsDataService $reports,
        protected ReportExportService $export,
        protected ReportRunService $reportRuns,
        protected ReportScheduleService $schedules,
        protected CustomReportBuilderService $customBuilder,
    ) {}

    protected function organizationScopeId(): ?int
    {
        $user = auth()->user();

        return $user->isSuperAdmin() ? null : $user->organization_id;
    }

    public function index(Request $request)
    {
        $orgId = $this->organizationScopeId();
        $period = $this->reports->parsePeriod($request->query('period'));
        $preset = $request->query('preset', 'month');
        $category = $request->query('category', 'financial');
        $search = $request->query('search');
        $viewAll = $request->boolean('view_all');

        $catalog = $this->reports->catalog($category, $search);
        $categoryReports = collect($catalog['reports'])->where('category', $category);
        $library = $viewAll
            ? $categoryReports->values()->all()
            : $categoryReports->take($category === 'financial' ? 5 : 8)->values()->all();

        $lastRuns = $this->reportRuns->lastRunLabels($orgId, $category);

        return view('pages.reports.index', [
            'period' => $period,
            'preset' => $preset,
            'category' => $category,
            'search' => $search,
            'viewAll' => $viewAll,
            'overview' => $this->reports->overview($orgId, $period, $preset),
            'catalog' => $catalog,
            'library' => $library,
            'lastRuns' => $lastRuns,
            'schedules' => $this->schedules->forUser(auth()->user(), $orgId),
            'periodOptions' => $this->reports->periodOptions($period),
            'prevPeriod' => $period->copy()->subMonth(),
            'nextPeriod' => $period->copy()->addMonth(),
        ], ['title' => 'Reports']);
    }

    public function show(Request $request, string $report)
    {
        $definition = config("reports.reports.{$report}");
        abort_if(! $definition, 404);

        $orgId = $this->organizationScopeId();
        $period = $this->reports->parsePeriod($request->query('period'));
        $filters = [
            'program' => $request->query('program', 'all'),
            'prompt' => $request->query('prompt'),
            'source' => $request->query('source'),
            'columns' => $request->query('columns'),
            'filter_chips' => $request->query('filter_chips'),
            'group_by' => $request->query('group_by'),
            'schedule' => $request->query('schedule'),
        ];

        $data = $this->reports->report($report, $orgId, $period, $filters);
        $this->reportRuns->record($report, $orgId, auth()->id(), $period, 'view', $this->export->countRows($data));

        $categoryMeta = config('reports.categories.'.$definition['category'], []);

        return view('pages.reports.show', [
            'report' => $report,
            'definition' => array_merge($definition, [
                'slug' => $report,
                'schedule_label' => config('reports.schedule_labels.'.$definition['schedule'], 'On demand'),
            ]),
            'categoryMeta' => $categoryMeta,
            'period' => $period,
            'filters' => $filters,
            'data' => $data,
            'view' => $definition['view'] ?? $report,
            'lastRun' => $this->reportRuns->lastRunFor($report, $orgId),
        ], ['title' => $definition['name']]);
    }

    public function export(Request $request, string $report): Response|StreamedResponse
    {
        $definition = config("reports.reports.{$report}");
        abort_if(! $definition, 404);

        $orgId = $this->organizationScopeId();
        $period = $this->reports->parsePeriod($request->query('period'));
        $format = in_array($request->query('format'), ['csv', 'xlsx', 'pdf'], true)
            ? $request->query('format')
            : 'csv';

        $data = $this->reports->report($report, $orgId, $period, $request->only([
            'program', 'prompt', 'source', 'columns', 'filter_chips', 'group_by',
        ]));

        $definition['name'] = $definition['name'] ?? $report;
        $periodLabel = $period->format('Y-m');

        $this->reportRuns->record(
            $report,
            $orgId,
            auth()->id(),
            $period,
            $format,
            $this->export->countRows($data),
        );

        return $this->export->download($definition, $data, $periodLabel, $format);
    }

    public function scheduleForm(Request $request)
    {
        $orgId = $this->organizationScopeId();

        return view('pages.reports.schedule', [
            'reportSlug' => $request->query('report'),
            'period' => $this->reports->parsePeriod($request->query('period')),
            'schedules' => $this->schedules->forUser(auth()->user(), $orgId),
            'reports' => collect(config('reports.reports', []))->map(fn ($r, $slug) => array_merge($r, ['slug' => $slug])),
        ], ['title' => 'Schedule Reports']);
    }

    public function scheduleStore(ScheduleReportRequest $request): RedirectResponse
    {
        $this->schedules->create(
            $request->user(),
            $request->validated(),
            $this->organizationScopeId(),
        );

        return redirect()
            ->route('reports.index', ['period' => $request->input('period')])
            ->with('success', 'Report scheduled to your inbox.');
    }

    public function scheduleDestroy(ReportSchedule $schedule): RedirectResponse
    {
        abort_if($schedule->user_id !== auth()->id() && ! auth()->user()->isSuperAdmin(), 403);
        $this->schedules->deactivate($schedule);

        return back()->with('success', 'Schedule removed.');
    }

    public function customRun(Request $request): JsonResponse|RedirectResponse
    {
        $orgId = $this->organizationScopeId();
        $prompt = $request->input('prompt', '');
        $parsed = $this->customBuilder->parsePrompt($prompt);
        $config = array_merge($parsed, $request->only(['source', 'group_by']));
        if ($request->filled('columns')) {
            $config['columns'] = is_array($request->input('columns'))
                ? $request->input('columns')
                : explode(',', $request->input('columns'));
        }

        $built = $this->customBuilder->buildPreview($orgId, $config);

        if ($request->expectsJson()) {
            return response()->json($built);
        }

        return redirect()->route('reports.show', [
            'report' => 'custom-builder',
            'prompt' => $prompt,
            'source' => $config['source'] ?? 'clients',
            'group_by' => $config['group_by'] ?? null,
        ]);
    }

    public function customSave(StoreCustomReportRequest $request): RedirectResponse
    {
        $def = $this->customBuilder->save(
            $request->user(),
            $this->organizationScopeId(),
            $request->validated(),
        );

        return redirect()
            ->route('reports.show', ['report' => 'custom-builder', 'saved' => $def->slug])
            ->with('success', 'Custom report saved.');
    }
}
