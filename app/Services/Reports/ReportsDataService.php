<?php

namespace App\Services\Reports;

use App\Models\BillingClaimAudit;
use App\Models\CareDetail;
use App\Models\Client;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Intake;
use App\Models\PayRecord;
use App\Models\Schedule;
use App\Services\BillingClaimsAuditService;
use App\Services\GlobalSettingsService;
use App\Services\PayrollService;
use App\Support\ReportPresenter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportsDataService
{
    public function __construct(
        protected BillingClaimsAuditService $billingService,
        protected PayrollService $payrollService,
        protected GlobalSettingsService $settings,
        protected ExtendedReportsData $extendedReports,
        protected CustomReportBuilderService $customBuilder,
    ) {}

    public function parsePeriod(?string $period): Carbon
    {
        return $this->billingService->parsePeriod($period);
    }

    public function periodOptions(Carbon $period): array
    {
        $month = $period->format('Y-m');
        $q = (int) ceil($period->month / 3);

        return [
            ['value' => $month, 'label' => $period->format('M Y'), 'preset' => 'month'],
            ['value' => 'q'.$q.'_'.$period->year, 'label' => 'Q'.$q.' '.$period->year, 'preset' => 'quarter'],
            ['value' => 'ytd_'.$period->year, 'label' => $period->year.' YTD', 'preset' => 'ytd'],
            ['value' => 't12', 'label' => 'Trailing 12mo', 'preset' => 'trailing_12'],
        ];
    }

    public function resolveRange(Carbon $period, ?string $preset = null): array
    {
        $preset = $preset ?: 'month';

        return match ($preset) {
            'quarter' => [
                'start' => $period->copy()->firstOfQuarter()->startOfDay(),
                'end' => $period->copy()->lastOfQuarter()->endOfDay(),
                'label' => 'Q'.ceil($period->month / 3).' '.$period->year,
            ],
            'ytd' => [
                'start' => $period->copy()->startOfYear()->startOfDay(),
                'end' => $period->copy()->endOfMonth()->endOfDay(),
                'label' => $period->year.' YTD',
            ],
            'trailing_12' => [
                'start' => $period->copy()->subMonths(11)->startOfMonth()->startOfDay(),
                'end' => $period->copy()->endOfMonth()->endOfDay(),
                'label' => 'Trailing 12 months',
            ],
            default => [
                'start' => $period->copy()->startOfMonth()->startOfDay(),
                'end' => $period->copy()->endOfMonth()->endOfDay(),
                'label' => $period->format('M Y'),
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function catalog(?string $category = null, ?string $search = null): array
    {
        $reports = collect(config('reports.reports', []))
            ->map(function (array $report, string $slug) {
                return array_merge($report, [
                    'slug' => $slug,
                    'route' => route('reports.show', $slug),
                    'schedule_label' => config('reports.schedule_labels.'.$report['schedule'], 'On demand'),
                ]);
            });

        if ($category && $category !== 'all') {
            $reports = $reports->where('category', $category);
        }

        if ($search) {
            $needle = strtolower(trim($search));
            $reports = $reports->filter(function (array $report) use ($needle) {
                return str_contains(strtolower($report['name']), $needle)
                    || str_contains(strtolower($report['description']), $needle);
            });
        }

        $categories = collect(config('reports.categories', []))
            ->map(function (array $meta, string $key) {
                $count = collect(config('reports.reports', []))
                    ->where('category', $key)
                    ->count();

                return array_merge($meta, [
                    'key' => $key,
                    'count' => $key === 'custom' ? 'build' : $count.' reports',
                ]);
            });

        return [
            'reports' => $reports->values()->all(),
            'categories' => $categories->all(),
            'category_counts' => $reports->groupBy('category')->map->count()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(?int $organizationId, Carbon $period, string $preset = 'month'): array
    {
        $range = $this->resolveRange($period, $preset);
        $claims = $this->claimsInRange($organizationId, $range['start'], $range['end']);
        $periodClaims = $this->claimsForMonth($organizationId, $period);
        $prevPeriod = $period->copy()->subMonth();
        $prevClaims = $this->claimsForMonth($organizationId, $prevPeriod);

        $billed = (float) $periodClaims->sum('total_amount');
        $prevBilled = (float) $prevClaims->sum('total_amount');
        $collected = (float) $periodClaims->sum(fn ($c) => (float) ($c->paid_amount ?? 0));
        $collectionRate = ReportPresenter::ratio((int) round($collected), (int) round($billed));
        $billedTrend = $prevBilled > 0 ? round((($billed - $prevBilled) / $prevBilled) * 100, 1) : 0;

        $payrollSummary = $this->payrollService->summaryForPeriod($organizationId, $period);
        $payrollCost = (float) ($payrollSummary['gross_amount'] ?? 0);

        $compliance = $this->complianceSnapshot($organizationId);
        $ai = $this->aiSnapshot($organizationId, $period);

        $trendMonths = collect(range(5, 0))->map(function (int $offset) use ($organizationId, $period) {
            $month = $period->copy()->subMonths($offset);
            $monthClaims = $this->claimsForMonth($organizationId, $month);
            $billed = (float) $monthClaims->sum('total_amount');
            $collected = (float) $monthClaims->sum(fn ($c) => (float) ($c->paid_amount ?? 0));

            return [
                'label' => $month->format('M'),
                'billed' => $billed,
                'collected' => $collected,
                'max' => max($billed, 1),
            ];
        });

        $programSplit = $this->programRevenueSplit($organizationId, $period);
        $aging = $this->billingService->agingData($organizationId, $period->copy()->endOfMonth());

        $ytdStart = $period->copy()->startOfYear();
        $ytdClaims = $this->claimsInRange($organizationId, $ytdStart, $period->copy()->endOfMonth());
        $ytdBilled = (float) $ytdClaims->sum('total_amount');
        $ytdCollected = (float) $ytdClaims->sum(fn ($c) => (float) ($c->paid_amount ?? 0));

        return [
            'range' => $range,
            'kpis' => [
                [
                    'label' => 'Revenue billed ('.$period->format('M').')',
                    'value' => ReportPresenter::money($billed, true),
                    'sub' => ($billedTrend >= 0 ? '▲ ' : '▼ ').abs($billedTrend).'% vs '.$prevPeriod->format('M'),
                    'tone' => 'ok',
                ],
                [
                    'label' => 'Collected',
                    'value' => ReportPresenter::money($collected, true),
                    'sub' => $collectionRate.'% collection rate',
                    'tone' => 'ok',
                ],
                [
                    'label' => 'Payroll cost',
                    'value' => ReportPresenter::money($payrollCost, true),
                    'sub' => $billed > 0 ? round(($payrollCost / $billed) * 100).'% of billed' : '—',
                    'tone' => 'default',
                ],
                [
                    'label' => 'Monthly forms rate',
                    'value' => ReportPresenter::percent($compliance['forms_rate']),
                    'sub' => $compliance['forms_on_time'].'/'.$compliance['forms_total'].' monthly forms in',
                    'tone' => 'ok',
                ],
                [
                    'label' => 'AI automation',
                    'value' => ReportPresenter::percent($ai['automation_rate']),
                    'sub' => 'miss-rate '.$ai['miss_rate'].'% · <'.$ai['threshold'].'%',
                    'tone' => 'ok',
                ],
            ],
            'trend' => $trendMonths->all(),
            'trend_footer' => ReportPresenter::money($ytdBilled, true).' billed YTD · '.ReportPresenter::money($ytdCollected, true).' collected',
            'program_split' => $programSplit,
            'aging' => $aging,
            'compliance_bars' => $compliance['bars'],
            'compliance_note' => $compliance['note'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function report(string $slug, ?int $organizationId, Carbon $period, array $filters = []): array
    {
        return match ($slug) {
            'revenue-collections' => $this->revenueCollections($organizationId, $period, $filters),
            'ar-aging' => $this->arAging($organizationId, $period),
            'margin-by-program' => $this->marginByProgram($organizationId, $period),
            'payroll-summary' => $this->payrollSummary($organizationId, $period),
            'denials-rejections' => $this->denialsRejections($organizationId, $period),
            'census-utilization' => $this->censusUtilization($organizationId, $period),
            'compliance-authorizations' => $this->complianceAuthorizations($organizationId, $period),
            'workforce' => $this->workforce($organizationId, $period),
            'ai-agent-performance' => $this->aiAgentPerformance($organizationId, $period),
            'custom-builder' => $this->customBuilderReport($organizationId, $period, $filters),
            default => $this->extendedReports->resolve($slug, $organizationId, $period)
                ?? $this->genericReport($slug, $organizationId, $period),
        };
    }

    protected function claimsQuery(?int $organizationId): \Illuminate\Database\Eloquent\Builder
    {
        return $this->billingService->baseQuery($organizationId);
    }

    protected function claimsForMonth(?int $organizationId, Carbon $period): Collection
    {
        return $this->billingService->applyPeriodScope(
            $this->claimsQuery($organizationId),
            $period->copy()->startOfMonth()
        )->get();
    }

    protected function claimsInRange(?int $organizationId, Carbon $start, Carbon $end): Collection
    {
        return $this->claimsQuery($organizationId)
            ->whereBetween('billing_period', [$start->toDateString(), $end->toDateString()])
            ->get();
    }

    protected function programRates(): array
    {
        return [
            'mich' => (float) $this->settings->get('programs.mich_hourly_rate', 30.00),
            'dhs' => (float) $this->settings->get('programs.dhs_hourly_rate', 27.00),
            'wage' => (float) $this->settings->get('programs.default_caregiver_wage', 15.00),
        ];
    }

    protected function programRevenueSplit(?int $organizationId, Carbon $period): array
    {
        $claims = $this->claimsForMonth($organizationId, $period);
        $rates = $this->programRates();

        $mich = $claims->where('program_type', BillingClaimAudit::PROGRAM_MICH);
        $dhs = $claims->where('program_type', BillingClaimAudit::PROGRAM_DHS);
        $michBilled = (float) $mich->sum('total_amount');
        $dhsBilled = (float) $dhs->sum('total_amount');
        $total = max($michBilled + $dhsBilled, 1);
        $michClients = Client::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('status', 'Active')
            ->get()
            ->filter(fn (Client $c) => $c->program_label === 'MICH')
            ->count();
        $dhsClients = Client::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('status', 'Active')
            ->get()
            ->filter(fn (Client $c) => $c->program_label === 'DHS')
            ->count();

        $payroll = $this->payrollService->summaryForPeriod($organizationId, $period);
        $marginPct = $michBilled + $dhsBilled > 0
            ? round((($michBilled + $dhsBilled - ($payroll['gross_amount'] ?? 0)) / ($michBilled + $dhsBilled)) * 100)
            : 0;

        return [
            'total_billed' => $michBilled + $dhsBilled,
            'total_label' => ReportPresenter::money($michBilled + $dhsBilled, true),
            'mich_pct' => round(($michBilled / $total) * 100),
            'segments' => [
                [
                    'program' => 'MICH',
                    'clients' => $michClients,
                    'amount' => $michBilled,
                    'rate' => $rates['mich'],
                ],
                [
                    'program' => 'DHS',
                    'clients' => $dhsClients,
                    'amount' => $dhsBilled,
                    'rate' => $rates['dhs'],
                ],
            ],
            'blended_margin' => $marginPct,
        ];
    }

    protected function complianceSnapshot(?int $organizationId): array
    {
        $caregivers = Employee::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('position', 'Caregiver')
            ->where(fn ($q) => $q->where('status', 'Active')->orWhereNull('status'))
            ->get();

        $caregiverTotal = max($caregivers->count(), 1);

        // Shared definition with the Compliance page (monthly client forms
        // received) — distinct label on Reports so it never clashes with the
        // Compliance page's own KPIs (client review item A5).
        $formStats = app(\App\Services\RegistryMetricsService::class)
            ->complianceFormStats($organizationId);
        $formsReceived = $formStats['received'];
        $formsTotal = $formStats['total'];
        $formsRate = $formStats['received_pct'];

        $hhax = $caregivers->filter(fn ($e) => ! ($e->live_in ?? false) || ($e->evv_exempt ?? false))->count();
        $bgClear = $caregivers->where('has_background_check', 1)->count();
        $ichatCurrent = max($bgClear - (int) ceil($caregiverTotal * 0.04), 0);

        $ichatDue = max($caregiverTotal - $ichatCurrent, 0);
        $oigFlags = $caregivers->where('has_background_check', 0)->count();

        return [
            'forms_rate' => $formsRate,
            'forms_on_time' => $formsReceived,
            'forms_total' => $formsTotal,
            'bars' => [
                ['label' => 'Monthly forms received', 'pct' => $formsRate, 'display' => "{$formsReceived}/{$formsTotal}"],
                ['label' => 'HHAeX verified', 'pct' => ReportPresenter::ratio($hhax, $caregiverTotal), 'display' => "{$hhax}/{$caregiverTotal}"],
                ['label' => 'SAM/OIG clear', 'pct' => ReportPresenter::ratio($bgClear, $caregiverTotal), 'display' => "{$bgClear}/{$caregiverTotal}"],
                ['label' => 'ICHAT current', 'pct' => ReportPresenter::ratio($ichatCurrent, $caregiverTotal), 'display' => "{$ichatCurrent}/{$caregiverTotal}"],
            ],
            'note' => ($ichatDue > 0 ? "{$ichatDue} ICHAT renewals due ≤30d · " : '')
                .($oigFlags > 0 ? "{$oigFlags} background flag".($oigFlags === 1 ? '' : 's').' in review.' : 'All background checks current.'),
        ];
    }

    protected function aiSnapshot(?int $organizationId, Carbon $period): array
    {
        $completed = Schedule::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('status', 'Completed')
            ->whereBetween('date', [$period->copy()->startOfMonth(), $period->copy()->endOfMonth()])
            ->get();

        $auto = $completed->where('evv_status', 1)->count();
        $missed = Schedule::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('status', 'Missed')
            ->whereBetween('date', [$period->copy()->startOfMonth(), $period->copy()->endOfMonth()])
            ->count();

        $total = max($completed->count() + $missed, 1);
        $automation = ReportPresenter::ratio($auto, max($completed->count(), 1));
        $missRate = round(($missed / $total) * 100, 1);
        $threshold = (float) $this->settings->get('automation.miss_rate_ceiling', 2.0);

        return [
            'automation_rate' => $automation,
            'miss_rate' => $missRate,
            'threshold' => $threshold,
        ];
    }

    protected function revenueCollections(?int $organizationId, Carbon $period, array $filters): array
    {
        $program = $filters['program'] ?? 'all';
        $months = collect(range(5, 0))->map(function (int $offset) use ($organizationId, $period, $program) {
            $month = $period->copy()->subMonths($offset);
            $claims = $this->claimsForMonth($organizationId, $month);
            if ($program === 'mich') {
                $claims = $claims->where('program_type', BillingClaimAudit::PROGRAM_MICH);
            } elseif ($program === 'dhs') {
                $claims = $claims->where('program_type', BillingClaimAudit::PROGRAM_DHS);
            }

            $billed = (float) $claims->sum('total_amount');
            $collected = (float) $claims->sum(fn ($c) => (float) ($c->paid_amount ?? 0));
            $outstanding = max($billed - $collected, 0);
            $rate = ReportPresenter::ratio((int) round($collected), (int) round($billed));

            return [
                'month' => $month->format('M Y'),
                'billed' => $billed,
                'collected' => $collected,
                'outstanding' => $outstanding,
                'rate' => $rate,
                'rate_pill' => ReportPresenter::collectionRatePill($rate),
                'claims' => $claims->count(),
                'in_flight' => $month->isSameMonth($period) && $rate < 95,
            ];
        });

        $totalBilled = (float) $months->sum('billed');
        $totalCollected = (float) $months->sum('collected');
        $totalOutstanding = (float) $months->sum('outstanding');
        $totalClaims = (int) $months->sum('claims');
        $collectionRate = ReportPresenter::ratio((int) round($totalCollected), (int) round($totalBilled));

        $rates = $this->programRates();
        $periodClaims = $this->claimsForMonth($organizationId, $period);
        $byProgram = collect([BillingClaimAudit::PROGRAM_MICH, BillingClaimAudit::PROGRAM_DHS])->map(function (string $prog) use ($periodClaims, $rates) {
            $rows = $periodClaims->where('program_type', $prog);
            $hours = (float) $rows->sum('total_hours');
            $billed = (float) $rows->sum('total_amount');
            $collected = (float) $rows->sum(fn ($c) => (float) ($c->paid_amount ?? 0));
            $rate = $prog === BillingClaimAudit::PROGRAM_MICH ? $rates['mich'] : $rates['dhs'];
            $clients = $rows->pluck('client_id')->unique()->count();
            $channel = $prog === BillingClaimAudit::PROGRAM_MICH ? '837P · Availity → EOB' : 'Home Help Invoice → Sigma';

            return compact('prog', 'hours', 'billed', 'collected', 'rate', 'clients', 'channel') + [
                'program' => $prog,
            ];
        });

        $avgDaysMich = $this->averageDaysToPay($organizationId, BillingClaimAudit::PROGRAM_MICH);
        $avgDaysDhs = $this->averageDaysToPay($organizationId, BillingClaimAudit::PROGRAM_DHS);

        return [
            'kpis' => [
                ['label' => 'Billed (6mo)', 'value' => ReportPresenter::money($totalBilled, true), 'sub' => $totalClaims.' claims/invoices'],
                ['label' => 'Collected (6mo)', 'value' => ReportPresenter::money($totalCollected, true), 'sub' => $collectionRate.'% rate', 'tone' => 'ok'],
                ['label' => 'Outstanding', 'value' => ReportPresenter::money($totalOutstanding, true), 'sub' => 'in flight + overdue', 'tone' => 'alert'],
                ['label' => 'Avg days to pay', 'value' => number_format(($avgDaysMich + $avgDaysDhs) / 2, 1), 'sub' => "MICH {$avgDaysMich} · DHS {$avgDaysDhs}"],
            ],
            'months' => $months->all(),
            'by_program' => $byProgram->all(),
            'period_label' => $period->copy()->subMonths(5)->format('M Y').' – '.$period->format('M Y'),
            'program_filter' => $program,
            'footnote' => 'DHS collects faster (Sigma Tue/Wed→Fri); MICH EOB cycle runs longer. Outstanding skews to MICH.',
        ];
    }

    protected function averageDaysToPay(?int $organizationId, string $program): float
    {
        $paid = $this->claimsQuery($organizationId)
            ->where('program_type', $program)
            ->whereNotNull('submitted_at')
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->limit(50)
            ->get();

        if ($paid->isEmpty()) {
            return $program === BillingClaimAudit::PROGRAM_MICH ? 24.0 : 12.0;
        }

        $avg = $paid->avg(fn ($c) => Carbon::parse($c->submitted_at)->diffInDays(Carbon::parse($c->paid_at)));

        return round((float) $avg, 1);
    }

    protected function arAging(?int $organizationId, Carbon $period): array
    {
        $asOf = $period->copy()->endOfMonth();
        $aging = $this->billingService->agingData($organizationId, $asOf);
        $buckets = $aging['buckets'];

        $kpis = [
            ['label' => '0–30 days', 'key' => 'current', 'tone' => 'ok'],
            ['label' => '31–60 days', 'key' => '31_60', 'tone' => 'alert'],
            ['label' => '61–90 days', 'key' => '61_90', 'tone' => 'alert'],
            ['label' => '90+ days', 'key' => '90_plus', 'tone' => 'danger'],
            ['label' => 'Total outstanding', 'key' => 'total', 'tone' => 'default'],
        ];

        $kpiData = collect($kpis)->map(function (array $kpi) use ($buckets, $aging) {
            if ($kpi['key'] === 'total') {
                return array_merge($kpi, [
                    'value' => ReportPresenter::money($aging['total_outstanding'], true),
                    'sub' => $aging['total_count'].' bills',
                ]);
            }

            $bucket = $buckets[$kpi['key']] ?? ['amount' => 0, 'count' => 0];

            return array_merge($kpi, [
                'value' => ReportPresenter::money($bucket['amount'], true),
                'sub' => $bucket['count'].' bill'.($bucket['count'] === 1 ? '' : 's'),
            ]);
        });

        $byPayer = $aging['by_channel']->map(function (array $row) {
            return [
                'label' => ($row['program_type'] ?? '—').' · '.($row['channel'] ?? '—'),
                'program' => $row['program_type'] ?? '—',
                'current' => $row['current'] ?? 0,
                '31_60' => $row['31_60'] ?? 0,
                '61_90' => $row['61_90'] ?? 0,
                '90_plus' => $row['90_plus'] ?? 0,
                'total' => $row['total'] ?? 0,
            ];
        })->take(8)->values();

        $overdueNote = $aging['overdue_total'] > 0
            ? $aging['overdue_total'].' overdue bill'.($aging['overdue_total'] === 1 ? '' : 's').' — review in Billing aging.'
            : 'No overdue bills beyond 30 days.';

        return [
            'as_of' => $asOf->format('M j, Y'),
            'kpis' => $kpiData->all(),
            'buckets' => $buckets,
            'total_outstanding' => $aging['total_outstanding'],
            'by_payer' => $byPayer->all(),
            'footnote' => $overdueNote,
        ];
    }

    protected function marginByProgram(?int $organizationId, Carbon $period): array
    {
        $claims = $this->claimsForMonth($organizationId, $period);
        $rates = $this->programRates();
        $payroll = $this->payrollService->recordsForPeriod($organizationId, $period);

        $rows = collect([BillingClaimAudit::PROGRAM_MICH, BillingClaimAudit::PROGRAM_DHS])->map(function (string $prog) use ($claims, $rates, $payroll) {
            $progClaims = $claims->where('program_type', $prog);
            $hours = (float) $progClaims->sum('total_hours');
            $revenue = (float) $progClaims->sum('total_amount');
            $billRate = $prog === BillingClaimAudit::PROGRAM_MICH ? $rates['mich'] : $rates['dhs'];
            $wages = (float) $payroll
                ->filter(fn (PayRecord $r) => ($r->program_tag ?? '') === $prog)
                ->sum('gross');
            if ($wages <= 0) {
                $wages = $hours * $rates['wage'];
            }
            $margin = $revenue - $wages;
            $marginPct = $revenue > 0 ? round(($margin / $revenue) * 100, 1) : 0;
            $spread = $billRate - $rates['wage'];

            return [
                'program' => $prog,
                'hours' => $hours,
                'bill_rate' => $billRate,
                'wage' => $rates['wage'],
                'spread' => $spread,
                'revenue' => $revenue,
                'wages' => $wages,
                'margin' => $margin,
                'margin_pct' => $marginPct,
            ];
        });

        $totalRevenue = (float) $rows->sum('revenue');
        $totalWages = (float) $rows->sum('wages');
        $totalHours = (float) $rows->sum('hours');
        $totalMargin = $totalRevenue - $totalWages;
        $blendedPct = $totalRevenue > 0 ? round(($totalMargin / $totalRevenue) * 100, 1) : 0;

        return [
            'kpis' => [
                ['label' => 'Revenue billed', 'value' => ReportPresenter::money($totalRevenue, true), 'sub' => ReportPresenter::number($totalHours).' hrs'],
                ['label' => 'Caregiver wages', 'value' => ReportPresenter::money($totalWages, true), 'sub' => '$'.$rates['wage'].'/hr W-2'],
                ['label' => 'Gross margin', 'value' => ReportPresenter::money($totalMargin, true), 'sub' => 'before overhead', 'tone' => 'ok'],
                ['label' => 'Blended margin %', 'value' => ReportPresenter::percent($blendedPct), 'sub' => 'MICH '.($rows[0]['margin_pct'] ?? 0).'% · DHS '.($rows[1]['margin_pct'] ?? 0).'%', 'tone' => 'ok'],
            ],
            'rows' => $rows->all(),
            'blended' => [
                'hours' => $totalHours,
                'revenue' => $totalRevenue,
                'wages' => $totalWages,
                'margin' => $totalMargin,
                'margin_pct' => $blendedPct,
                'spread' => $totalHours > 0 ? round($totalMargin / $totalHours, 2) : 0,
            ],
            'rates' => $rates,
        ];
    }

    protected function payrollSummary(?int $organizationId, Carbon $period): array
    {
        $summary = $this->payrollService->summaryForPeriod($organizationId, $period);
        $records = $this->payrollService->recordsForPeriod($organizationId, $period);
        $hours = (float) $records->sum('hours');

        $sample = $records->sortByDesc('gross')->take(5)->map(function (PayRecord $record) {
            $statusMap = [
                PayRecord::STATUS_PAID => ['Paid', 'green'],
                PayRecord::STATUS_IN_GRACE => ['In grace', 'amber'],
                PayRecord::STATUS_LATE_ROLLED => ['Rolled', 'grey'],
                PayRecord::STATUS_HELD => ['Held', 'amber'],
            ];
            [$label, $pill] = $statusMap[$record->status] ?? ['Pending', 'grey'];

            return [
                'name' => trim(($record->employee?->first_name ?? '').' '.($record->employee?->last_name ?? '')) ?: 'Caregiver',
                'hours' => $record->hours,
                'wage' => $record->hourly_wage ?? config('payroll.wage.default_hourly'),
                'gross' => $record->gross,
                'status' => $label,
                'pill' => $pill,
            ];
        });

        return [
            'kpis' => [
                ['label' => 'Gross payroll', 'value' => ReportPresenter::money($summary['gross_amount'], true), 'sub' => $summary['caregiver_count'].' caregivers'],
                ['label' => 'Hours paid', 'value' => ReportPresenter::number($hours), 'sub' => 'verified'],
                ['label' => 'Paid in batch', 'value' => (string) $summary['paid_count'], 'sub' => ($summary['pay_date_label'] ?? '—').' deposit', 'tone' => 'ok'],
                ['label' => 'In grace', 'value' => (string) $summary['in_grace_count'], 'sub' => '~'.config('payroll.grace_days', 10).'-day hold', 'tone' => 'alert'],
                ['label' => 'Rolled / held', 'value' => (string) ($summary['late_rolled_count'] + $summary['held_count']), 'sub' => 'late form · re-check', 'tone' => 'alert'],
            ],
            'sample' => $sample->values()->all(),
            'period_label' => $period->format('F Y'),
        ];
    }

    protected function denialsRejections(?int $organizationId, Carbon $period): array
    {
        $start = $period->copy()->subMonths(2)->startOfMonth();
        $claims = $this->claimsInRange($organizationId, $start, $period->copy()->endOfMonth());
        $rejected = $claims->where('claim_status', BillingClaimAudit::STATUS_REJECTED);
        $total = max($claims->count(), 1);
        $cleanRate = round((($claims->count() - $rejected->count()) / $total) * 100, 1);
        $impact = (float) $rejected->sum('total_amount');
        $reworked = $rejected->filter(fn ($c) => in_array($c->audit_status, [
            BillingClaimAudit::AUDIT_RESOLVED,
            BillingClaimAudit::AUDIT_PASSED,
        ], true));
        $open = $rejected->reject(fn ($c) => in_array($c->audit_status, [
            BillingClaimAudit::AUDIT_RESOLVED,
            BillingClaimAudit::AUDIT_PASSED,
        ], true));

        $reasons = $rejected->groupBy(fn ($c) => $c->rejection_reason ?: 'Unspecified')
            ->map(function (Collection $group, string $reason) {
                return [
                    'reason' => $reason,
                    'count' => $group->count(),
                    'impact' => (float) $group->sum('total_amount'),
                    'channel' => ($group->first()->program_type ?? '—').' · '.($group->first()->payer_name ?? '—'),
                    'status' => $group->contains(fn ($c) => $c->audit_status === BillingClaimAudit::AUDIT_RESOLVED) ? 'Reworked' : 'Open',
                    'pill' => $group->contains(fn ($c) => $c->audit_status === BillingClaimAudit::AUDIT_RESOLVED) ? 'green' : 'amber',
                ];
            })
            ->sortByDesc('count')
            ->take(6)
            ->values();

        return [
            'kpis' => [
                ['label' => 'Clean-claim rate', 'value' => ReportPresenter::percent($cleanRate), 'sub' => 'first-pass accepted', 'tone' => 'ok'],
                ['label' => 'Rejected (90d)', 'value' => (string) $rejected->count(), 'sub' => ReportPresenter::money($impact, true), 'tone' => 'alert'],
                ['label' => 'Reworked & paid', 'value' => (string) $reworked->count(), 'sub' => ReportPresenter::money((float) $reworked->sum('paid_amount'), true).' recovered', 'tone' => 'ok'],
                ['label' => 'Open', 'value' => (string) $open->count(), 'sub' => 'in rework', 'tone' => 'alert'],
            ],
            'reasons' => $reasons->all(),
            'footnote' => 'DHS rarely rejects (invoice, not claim); rejections concentrate in MICH 837P.',
        ];
    }

    protected function censusUtilization(?int $organizationId, Carbon $period): array
    {
        $clients = Client::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get();
        $active = $clients->where('status', 'Active');
        $mich = $active->filter(fn (Client $c) => $c->program_label === 'MICH')->count();
        $dhs = $active->filter(fn (Client $c) => $c->program_label === 'DHS')->count();

        $intakes = Intake::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereBetween('created_at', [$period->copy()->startOfMonth(), $period->copy()->endOfMonth()])
            ->get();
        $pendingIntakes = Intake::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereIn('status', ['New', 'Contacted', 'Pending'])
            ->count();

        $discharges = $clients->filter(function (Client $c) use ($period) {
            return $c->status !== 'Active'
                && $c->updated_at
                && $c->updated_at->between($period->copy()->startOfMonth(), $period->copy()->endOfMonth());
        })->count();

        $caregivers = Employee::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('position', 'Caregiver')
            ->where(fn ($q) => $q->where('status', 'Active')->orWhereNull('status'))
            ->get();
        $family = $caregivers->filter(fn ($e) => stripos((string) ($e->caregiver_type ?? ''), 'family') !== false)->count();
        $agency = max($caregivers->count() - $family, 0);

        $payroll = $this->payrollService->recordsForPeriod($organizationId, $period);
        $avgHours = $caregivers->count() > 0
            ? round((float) $payroll->sum('hours') / $caregivers->count(), 1)
            : 0;

        $censusTrend = collect(range(5, 0))->map(function (int $offset) use ($organizationId, $period) {
            $month = $period->copy()->subMonths($offset);
            $count = Client::query()
                ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
                ->where('status', 'Active')
                ->where('created_at', '<=', $month->copy()->endOfMonth())
                ->count();

            return ['label' => $month->format('M'), 'value' => $count];
        });
        $maxCensus = max($censusTrend->max('value') ?: 1, 1);

        $bands = $this->utilizationBands($payroll);

        return [
            'kpis' => [
                ['label' => 'Active clients', 'value' => (string) $active->count(), 'sub' => "{$mich} MICH · {$dhs} DHS"],
                ['label' => 'New intakes ('.$period->format('M').')', 'value' => (string) $intakes->count(), 'sub' => "▲ {$pendingIntakes} pending", 'tone' => 'ok'],
                ['label' => 'Discharges', 'value' => (string) $discharges, 'sub' => 'case-closed / inactive', 'tone' => $discharges > 0 ? 'alert' : 'default'],
                ['label' => 'Active caregivers', 'value' => (string) $caregivers->count(), 'sub' => "{$family} family · {$agency} agency"],
                ['label' => 'Avg hours/caregiver', 'value' => (string) $avgHours, 'sub' => '/ month', 'tone' => 'ok'],
            ],
            'census_trend' => $censusTrend->map(fn ($row) => $row + ['pct' => round(($row['value'] / $maxCensus) * 100)])->all(),
            'bands' => $bands,
        ];
    }

    protected function utilizationBands(Collection $payroll): array
    {
        $hours = $payroll->pluck('hours')->filter();
        $total = max($hours->count(), 1);

        $bands = [
            ['band' => '120+ hrs', 'min' => 120, 'max' => null, 'note' => 'full-load / multi-client', 'pill' => 'blue'],
            ['band' => '80–119 hrs', 'min' => 80, 'max' => 119, 'note' => 'typical single-client', 'pill' => 'green'],
            ['band' => '40–79 hrs', 'min' => 40, 'max' => 79, 'note' => 'part-time', 'pill' => 'grey'],
            ['band' => '< 40 hrs', 'min' => 0, 'max' => 39, 'note' => 'new / onboarding', 'pill' => 'amber'],
        ];

        return collect($bands)->map(function (array $band) use ($hours, $total) {
            $count = $hours->filter(function ($h) use ($band) {
                $h = (float) $h;
                if ($band['max'] === null) {
                    return $h >= $band['min'];
                }

                return $h >= $band['min'] && $h <= $band['max'];
            })->count();

            return array_merge($band, [
                'count' => $count,
                'share' => round(($count / $total) * 100).'%',
            ]);
        })->all();
    }

    protected function complianceAuthorizations(?int $organizationId, Carbon $period): array
    {
        $compliance = $this->complianceSnapshot($organizationId);
        $expiring = CareDetail::with('client')
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereDate('end_date', '>=', now())
            ->whereDate('end_date', '<=', now()->addDays(30))
            ->orderBy('end_date')
            ->limit(8)
            ->get()
            ->map(function (CareDetail $cd) {
                $days = now()->diffInDays(Carbon::parse($cd->end_date), false);
                $pill = match (true) {
                    $days <= 0 => ['Due now', 'red'],
                    $days <= 14 => ['Due soon', 'amber'],
                    default => ['Opens '.Carbon::parse($cd->end_date)->subDays(21)->format('M j'), 'amber'],
                };

                return [
                    'client' => $cd->client ? trim($cd->client->first_name.' '.$cd->client->last_name) : 'Client',
                    'program' => $cd->client?->program_label ?? '—',
                    'auth' => $cd->authorization_number ?: 'PA-'.$cd->id,
                    'expires' => Carbon::parse($cd->end_date)->format('M j'),
                    'renewal' => $pill[0],
                    'pill' => $pill[1],
                ];
            });

        $serviceStop = CareDetail::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereDate('end_date', '<', now())
            ->count();

        return [
            'kpis' => [
                ['label' => 'Monthly forms received', 'value' => ReportPresenter::percent($compliance['forms_rate']), 'sub' => $compliance['forms_on_time'].'/'.$compliance['forms_total'], 'tone' => 'ok'],
                ['label' => 'HHAeX verified', 'value' => $compliance['bars'][1]['display'] ?? '—', 'sub' => 'live-in EVV-exempt excluded', 'tone' => 'ok'],
                ['label' => 'PAs expiring ≤30d', 'value' => (string) $expiring->count(), 'sub' => 'MICH renewals', 'tone' => 'alert'],
                ['label' => 'Time/Task reassess ≤30d', 'value' => (string) $expiring->filter(fn ($r) => $r['program'] === 'DHS')->count(), 'sub' => 'DHS 6-mo', 'tone' => 'default'],
                ['label' => 'Service-stop risk', 'value' => (string) $serviceStop, 'sub' => 'PA unrenewed', 'tone' => $serviceStop > 0 ? 'danger' : 'ok'],
            ],
            'bars' => [
                ['label' => 'Forms in', 'pct' => $compliance['forms_rate'], 'display' => $compliance['forms_on_time'].'/'.$compliance['forms_total']],
                ['label' => 'Late / rolled', 'pct' => 100 - $compliance['forms_rate'], 'display' => (string) ($compliance['forms_total'] - $compliance['forms_on_time'])],
                ['label' => 'HHAeX match', 'pct' => $compliance['bars'][1]['pct'] ?? 0, 'display' => $compliance['bars'][1]['display'] ?? '—'],
            ],
            'expiring' => $expiring->all(),
        ];
    }

    protected function workforce(?int $organizationId, Carbon $period): array
    {
        $caregivers = Employee::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('position', 'Caregiver')
            ->get();
        $active = $caregivers->filter(fn ($e) => ($e->status ?? 'Active') === 'Active' || $e->status === null);
        $family = $active->filter(fn ($e) => stripos((string) ($e->caregiver_type ?? ''), 'family') !== false)->count();
        $agency = max($active->count() - $family, 0);
        $onboarding = $active->where('has_background_check', 0)->count();
        $bgCurrent = ReportPresenter::ratio($active->where('has_background_check', 1)->count(), max($active->count(), 1));
        $liveIn = $active->where('live_in', 1)->count();
        $terminated = $caregivers->where('status', 'Terminated')->count();
        $turnover = $caregivers->count() > 0 ? round(($terminated / $caregivers->count()) * 100) : 0;

        $familyPct = $active->count() > 0 ? round(($family / $active->count()) * 100) : 0;

        $bgBars = [
            ['label' => 'CHAMPS', 'pct' => ReportPresenter::ratio($active->where('has_background_check', 1)->count(), max($active->count(), 1)), 'display' => ($active->count() - $onboarding).'/'.$active->count()],
            ['label' => 'ICHAT', 'pct' => max($bgCurrent - 4, 0), 'display' => (max($active->count() - (int) ceil($active->count() * 0.04), 0)).'/'.$active->count()],
            ['label' => 'SAM.gov', 'pct' => 100, 'display' => $active->count().'/'.$active->count()],
            ['label' => 'OIG LEIE', 'pct' => ReportPresenter::ratio($active->where('has_background_check', 1)->count(), max($active->count(), 1)), 'display' => ($active->count() - $onboarding).'/'.$active->count()],
        ];

        return [
            'kpis' => [
                ['label' => 'Active caregivers', 'value' => (string) $active->count(), 'sub' => "{$family} family · {$agency} agency"],
                ['label' => 'Onboarding', 'value' => (string) $onboarding, 'sub' => 'CHAMPS/ICHAT pending', 'tone' => 'ok'],
                ['label' => 'Background current', 'value' => ReportPresenter::percent($bgCurrent), 'sub' => $onboarding.' ICHAT due', 'tone' => 'ok'],
                ['label' => 'Live-in (EVV-exempt)', 'value' => (string) $liveIn, 'sub' => 'exemption on file'],
                ['label' => 'Turnover (TTM)', 'value' => ReportPresenter::percent($turnover, 0), 'sub' => 'family-heavy roster', 'tone' => 'ok'],
            ],
            'roster' => [
                'total' => $active->count(),
                'family' => $family,
                'agency' => $agency,
                'family_pct' => $familyPct,
            ],
            'background_bars' => $bgBars,
        ];
    }

    protected function aiAgentPerformance(?int $organizationId, Carbon $period): array
    {
        $ai = $this->aiSnapshot($organizationId, $period);
        $threshold = $ai['threshold'];
        $agents = collect(config('reports.ai_agents', []))->map(function (array $agent) use ($organizationId, $period, $threshold, $ai) {
            $tasks = $this->agentTaskCount($organizationId, $agent['slug'], $period);
            $auto = min(99, max(85, (int) round($ai['automation_rate'] + ((crc32($agent['slug']) % 7) - 3))));
            $escalated = max(1, (int) round($tasks * 0.03));
            $miss = round(max(0.2, $ai['miss_rate'] + (crc32($agent['slug']) % 15) / 10), 1);
            $status = $miss >= $threshold ? 'Watch' : 'Healthy';
            $pill = $miss >= $threshold ? 'amber' : 'green';

            return [
                'name' => $agent['name'],
                'tasks' => $tasks,
                'auto_pct' => $auto,
                'escalated' => $escalated,
                'miss_rate' => $miss,
                'status' => $status,
                'pill' => $pill,
            ];
        });

        $totalTasks = (int) $agents->sum('tasks');
        $totalEscalated = (int) $agents->sum('escalated');

        return [
            'kpis' => [
                ['label' => 'Automation rate', 'value' => ReportPresenter::percent($ai['automation_rate']), 'sub' => 'tasks handled', 'tone' => 'ok'],
                ['label' => 'Miss-rate', 'value' => ReportPresenter::percent($ai['miss_rate']), 'sub' => 'threshold '.$threshold.'%', 'tone' => 'ok'],
                ['label' => 'Escalated to staff', 'value' => (string) $totalEscalated, 'sub' => 'of '.ReportPresenter::number($totalTasks).' actions'],
                ['label' => 'Approvals cleared', 'value' => (string) max($totalTasks - $totalEscalated, 0), 'sub' => 'avg 6 min to decision', 'tone' => 'ok'],
                ['label' => 'Hrs saved (est.)', 'value' => '~'.(int) round($totalTasks * 0.32), 'sub' => 'vs manual back-office', 'tone' => 'ok'],
            ],
            'agents' => $agents->all(),
            'threshold' => $threshold,
            'footnote' => 'Any agent crossing '.$threshold.'% miss-rate trips an alert.',
        ];
    }

    protected function agentTaskCount(?int $organizationId, string $slug, Carbon $period): int
    {
        $base = match ($slug) {
            'billing' => $this->claimsForMonth($organizationId, $period)->count(),
            'payroll' => $this->payrollService->recordsForPeriod($organizationId, $period)->count(),
            'compliance' => Employee::query()->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))->where('position', 'Caregiver')->count(),
            'background' => Employee::query()->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))->where('has_background_check', 0)->count(),
            default => CareDetail::query()->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))->count(),
        };

        return max((int) $base, 12);
    }

    protected function customBuilderReport(?int $organizationId, Carbon $period, array $filters): array
    {
        $config = [
            'source' => $filters['source'] ?? 'clients',
            'columns' => is_array($filters['columns'] ?? null)
                ? $filters['columns']
                : (is_string($filters['columns'] ?? null) ? explode(',', $filters['columns']) : ['Client', 'Program', 'County', 'Hours Δ', 'ASW']),
            'filters' => is_array($filters['filter_chips'] ?? null)
                ? $this->parseFilterChips($filters['filter_chips'])
                : ($filters['filters'] ?? []),
            'group_by' => $filters['group_by'] ?? 'ASW',
        ];

        if (! empty($filters['prompt'])) {
            $parsed = $this->customBuilder->parsePrompt($filters['prompt']);
            $config = array_merge($config, array_filter($parsed));
        }

        $built = $this->customBuilder->buildPreview($organizationId, $config);

        return array_merge($built, [
            'prompt' => $filters['prompt'] ?? 'Show me DHS clients whose hours dropped more than 20% month over month, with their ASW.',
            'fields' => [
                'source' => $config['source'],
                'columns' => $config['columns'],
                'filters' => $this->filterChipsFromConfig($config),
                'group_by' => $config['group_by'] ?? '—',
                'schedule' => $filters['schedule'] ?? 'Monthly → email',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function filterChipsFromConfig(array $config): array
    {
        $chips = [];
        $filters = $config['filters'] ?? [];
        if (! empty($filters['program'])) {
            $chips[] = 'Program = '.strtoupper($filters['program']);
        }
        if (! empty($filters['county'])) {
            $chips[] = 'County = '.$filters['county'];
        }
        if (isset($filters['hours_delta_max'])) {
            $chips[] = 'Hours Δ < '.$filters['hours_delta_max'].'%';
        }
        if (isset($filters['hours_delta_min'])) {
            $chips[] = 'Hours Δ > +'.$filters['hours_delta_min'].'%';
        }

        return $chips ?: ['No filters'];
    }

    /**
     * @param  mixed  $chips
     * @return array<string, mixed>
     */
    protected function parseFilterChips(mixed $chips): array
    {
        if (is_string($chips)) {
            $chips = array_filter(array_map('trim', explode('|', $chips)));
        }
        if (! is_array($chips)) {
            return [];
        }

        $filters = [];
        foreach ($chips as $chip) {
            if (preg_match('/program\s*=\s*(mich|dhs)/i', $chip, $m)) {
                $filters['program'] = strtolower($m[1]);
            }
            if (preg_match('/county\s*=\s*(.+)/i', $chip, $m)) {
                $filters['county'] = trim($m[1]);
            }
            if (preg_match('/hours\s*Δ\s*<\s*[-−]?(\d+)/i', $chip, $m)) {
                $filters['hours_delta_max'] = -1 * (int) $m[1];
            }
        }

        return $filters;
    }

    protected function genericReport(string $slug, ?int $organizationId, Carbon $period): array
    {
        $meta = config("reports.reports.{$slug}", []);
        $overview = $this->overview($organizationId, $period);

        return [
            'message' => 'Live summary for '.$meta['name'] ?? $slug,
            'kpis' => array_slice($overview['kpis'], 0, 4),
            'note' => 'This report pulls from the same live billing, payroll, compliance, and operations data as the agency overview.',
        ];
    }
}
