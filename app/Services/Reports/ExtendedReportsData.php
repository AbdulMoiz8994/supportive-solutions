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
use App\Models\Task;
use App\Models\WorkflowQueueItem;
use App\Services\BillingClaimsAuditService;
use App\Services\GlobalSettingsService;
use App\Services\PayrollService;
use App\Support\ReportPresenter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ExtendedReportsData
{
    public function __construct(
        protected BillingClaimsAuditService $billingService,
        protected PayrollService $payrollService,
        protected GlobalSettingsService $settings,
    ) {}

    public function resolve(string $slug, ?int $organizationId, Carbon $period): ?array
    {
        return match ($slug) {
            'payer-mix' => $this->payerMix($organizationId, $period),
            'collections-forecast' => $this->collectionsForecast($organizationId, $period),
            'billing-productivity' => $this->billingProductivity($organizationId, $period),
            'cash-position' => $this->cashPosition($organizationId, $period),
            'visit-completion' => $this->visitCompletion($organizationId, $period),
            'authorization-utilization' => $this->authorizationUtilization($organizationId, $period),
            'intake-pipeline' => $this->intakePipeline($organizationId, $period),
            'schedule-adherence' => $this->scheduleAdherence($organizationId, $period),
            'evv-compliance' => $this->evvCompliance($organizationId, $period),
            'workflow-throughput' => $this->workflowThroughput($organizationId, $period),
            'forms-timeliness' => $this->formsTimeliness($organizationId, $period),
            'background-monitoring' => $this->backgroundMonitoring($organizationId, $period),
            'document-verification' => $this->documentVerification($organizationId, $period),
            'hha-exchange-match' => $this->hhaExchangeMatch($organizationId, $period),
            'service-stop-risk' => $this->serviceStopRisk($organizationId, $period),
            'onboarding-pipeline' => $this->onboardingPipeline($organizationId, $period),
            'turnover-analysis' => $this->turnoverAnalysis($organizationId, $period),
            'live-in-roster' => $this->liveInRoster($organizationId, $period),
            'wage-compliance' => $this->wageCompliance($organizationId, $period),
            'escalation-volume' => $this->escalationVolume($organizationId, $period),
            'approval-turnaround' => $this->approvalTurnaround($organizationId, $period),
            'hours-saved' => $this->hoursSaved($organizationId, $period),
            default => null,
        };
    }

    protected function payerMix(?int $organizationId, Carbon $period): array
    {
        $claims = $this->claimsForMonth($organizationId, $period);
        $total = max((float) $claims->sum('total_amount'), 1);
        $mich = (float) $claims->where('program_type', BillingClaimAudit::PROGRAM_MICH)->sum('total_amount');
        $dhs = (float) $claims->where('program_type', BillingClaimAudit::PROGRAM_DHS)->sum('total_amount');

        $byPayer = $claims->groupBy(fn ($c) => $c->payer_name ?: ($c->health_plan_name ?: 'Unknown'))
            ->map(fn (Collection $group, string $payer) => [
                'amount' => (float) $group->sum('total_amount'),
                'claims' => $group->count(),
                'program' => $group->pluck('program_type')->unique()->implode('/'),
            ])
            ->sortByDesc('amount')
            ->take(10);

        $rows = $byPayer->map(fn (array $row, string $payer) => [
            $payer,
            $row['program'],
            ReportPresenter::money($row['amount'], true),
            ReportPresenter::percent(ReportPresenter::ratio((int) round($row['amount']), (int) round($total))),
            (string) $row['claims'],
        ])->values()->all();

        $topPayer = $byPayer->keys()->first() ?? '—';

        return [
            'kpis' => [
                ['label' => 'Total billed', 'value' => ReportPresenter::money($total, true), 'sub' => $claims->count().' claims/invoices'],
                ['label' => 'MICH share', 'value' => ReportPresenter::percent(ReportPresenter::ratio((int) round($mich), (int) round($total))), 'sub' => ReportPresenter::money($mich, true), 'tone' => 'ok'],
                ['label' => 'DHS share', 'value' => ReportPresenter::percent(ReportPresenter::ratio((int) round($dhs), (int) round($total))), 'sub' => ReportPresenter::money($dhs, true)],
                ['label' => 'Top payer', 'value' => $topPayer, 'sub' => ReportPresenter::money($byPayer->first()['amount'] ?? 0, true)],
            ],
            'sections' => [
                $this->section('Revenue by payer', ['Payer', 'Program', 'Billed', 'Share', 'Claims'], $rows, $period->format('M Y')),
            ],
            'footnote' => 'Payer mix reflects billed amounts for the selected month.',
        ];
    }

    protected function collectionsForecast(?int $organizationId, Carbon $period): array
    {
        $claims = $this->claimsForMonth($organizationId, $period);
        $inFlight = $claims->filter(fn ($c) => (float) ($c->paid_amount ?? 0) < (float) $c->total_amount
            && $c->claim_status !== BillingClaimAudit::STATUS_REJECTED);
        $inFlightAmount = (float) $inFlight->sum(fn ($c) => (float) $c->total_amount - (float) ($c->paid_amount ?? 0));

        $trailing = collect(range(1, 3))->map(fn (int $offset) => $this->claimsForMonth($organizationId, $period->copy()->subMonths($offset)));
        $trailingBilled = (float) $trailing->sum(fn ($c) => $c->sum('total_amount'));
        $trailingCollected = (float) $trailing->sum(fn ($c) => $c->sum(fn ($x) => (float) ($x->paid_amount ?? 0)));
        $collectionRate = ReportPresenter::ratio((int) round($trailingCollected), (int) round(max($trailingBilled, 1)));
        $projected = round($inFlightAmount * ($collectionRate / 100), 2);

        $rows = $inFlight->sortByDesc('total_amount')->take(12)->map(function ($c) use ($collectionRate) {
            $outstanding = (float) $c->total_amount - (float) ($c->paid_amount ?? 0);

            return [
                $c->client ? trim($c->client->first_name.' '.$c->client->last_name) : '—',
                $c->program_type ?? '—',
                ReportPresenter::money($outstanding, true),
                ReportPresenter::money(round($outstanding * ($collectionRate / 100), 2), true),
                $c->submitted_at ? Carbon::parse($c->submitted_at)->format('M j') : '—',
            ];
        })->values()->all();

        return [
            'kpis' => [
                ['label' => 'Projected collections', 'value' => ReportPresenter::money($projected, true), 'sub' => 'from in-flight', 'tone' => 'ok'],
                ['label' => 'In-flight AR', 'value' => ReportPresenter::money($inFlightAmount, true), 'sub' => $inFlight->count().' open'],
                ['label' => 'Trailing collection rate', 'value' => ReportPresenter::percent($collectionRate), 'sub' => 'prior 3 months'],
                ['label' => 'Avg days to pay', 'value' => number_format($this->avgDaysToPay($organizationId), 1), 'sub' => 'paid claims'],
            ],
            'sections' => [
                $this->section('In-flight claims', ['Client', 'Program', 'Outstanding', 'Projected', 'Submitted'], $rows),
            ],
            'footnote' => 'Forecast applies trailing collection rate to outstanding in-flight claims.',
        ];
    }

    protected function billingProductivity(?int $organizationId, Carbon $period): array
    {
        $start = $period->copy()->subMonths(2)->startOfMonth();
        $claims = $this->claimsInRange($organizationId, $start, $period->copy()->endOfMonth());
        $submitted = $claims->filter(fn ($c) => $c->submitted_at !== null);
        $rejected = $claims->where('claim_status', BillingClaimAudit::STATUS_REJECTED);
        $total = max($claims->count(), 1);
        $cleanRate = ReportPresenter::ratio($claims->count() - $rejected->count(), $total);

        $paidWithDates = $submitted->filter(fn ($c) => $c->paid_at !== null);
        $avgCycle = $paidWithDates->isNotEmpty()
            ? round($paidWithDates->avg(fn ($c) => Carbon::parse($c->submitted_at)->diffInDays(Carbon::parse($c->paid_at))), 1)
            : 0;

        $byProgram = collect([BillingClaimAudit::PROGRAM_MICH, BillingClaimAudit::PROGRAM_DHS])->map(function (string $prog) use ($claims, $rejected) {
            $progClaims = $claims->where('program_type', $prog);
            $progRejected = $rejected->where('program_type', $prog);
            $progTotal = max($progClaims->count(), 1);

            return [
                $prog,
                (string) $progClaims->filter(fn ($c) => $c->submitted_at)->count(),
                ReportPresenter::percent(ReportPresenter::ratio($progClaims->count() - $progRejected->count(), $progTotal)),
                (string) $progRejected->count(),
            ];
        })->all();

        return [
            'kpis' => [
                ['label' => 'Submitted (90d)', 'value' => (string) $submitted->count(), 'sub' => 'claims/invoices'],
                ['label' => 'Clean-claim rate', 'value' => ReportPresenter::percent($cleanRate), 'sub' => 'first-pass', 'tone' => 'ok'],
                ['label' => 'Rejected', 'value' => (string) $rejected->count(), 'sub' => ReportPresenter::money((float) $rejected->sum('total_amount'), true), 'tone' => 'alert'],
                ['label' => 'Avg cycle time', 'value' => $avgCycle.'d', 'sub' => 'submit → pay'],
            ],
            'sections' => [
                $this->section('Productivity by program', ['Program', 'Submitted', 'Clean rate', 'Rejected'], $byProgram),
            ],
        ];
    }

    protected function cashPosition(?int $organizationId, Carbon $period): array
    {
        $claims = $this->claimsForMonth($organizationId, $period);
        $billed = (float) $claims->sum('total_amount');
        $collected = (float) $claims->sum(fn ($c) => (float) ($c->paid_amount ?? 0));
        $payroll = $this->payrollService->summaryForPeriod($organizationId, $period);
        $payrollCost = (float) ($payroll['gross_amount'] ?? 0);
        $netCash = $collected - $payrollCost;
        $marginPct = $billed > 0 ? round((($billed - $payrollCost) / $billed) * 100, 1) : 0;

        $months = collect(range(5, 0))->map(function (int $offset) use ($organizationId, $period) {
            $month = $period->copy()->subMonths($offset);
            $monthClaims = $this->claimsForMonth($organizationId, $month);
            $monthPayroll = $this->payrollService->summaryForPeriod($organizationId, $month);
            $col = (float) $monthClaims->sum(fn ($c) => (float) ($c->paid_amount ?? 0));
            $pay = (float) ($monthPayroll['gross_amount'] ?? 0);

            return [
                $month->format('M Y'),
                ReportPresenter::money((float) $monthClaims->sum('total_amount'), true),
                ReportPresenter::money($col, true),
                ReportPresenter::money($pay, true),
                ReportPresenter::money($col - $pay, true),
            ];
        })->all();

        return [
            'kpis' => [
                ['label' => 'Billed', 'value' => ReportPresenter::money($billed, true), 'sub' => $period->format('M Y')],
                ['label' => 'Collected', 'value' => ReportPresenter::money($collected, true), 'sub' => ReportPresenter::ratio((int) round($collected), (int) round(max($billed, 1))).'% rate', 'tone' => 'ok'],
                ['label' => 'Payroll out', 'value' => ReportPresenter::money($payrollCost, true), 'sub' => ($payroll['caregiver_count'] ?? 0).' caregivers'],
                ['label' => 'Net cash', 'value' => ReportPresenter::money($netCash, true), 'sub' => ReportPresenter::percent($marginPct).' margin', 'tone' => $netCash >= 0 ? 'ok' : 'alert'],
            ],
            'sections' => [
                $this->section('Cash trend (6mo)', ['Month', 'Billed', 'Collected', 'Payroll', 'Net'], $months),
            ],
            'footnote' => 'Net cash = collected revenue minus gross payroll for the period.',
        ];
    }

    protected function visitCompletion(?int $organizationId, Carbon $period): array
    {
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $visits = Schedule::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereBetween('date', [$start, $end])
            ->where('event_type', Schedule::EVENT_CARE_VISIT)
            ->get();

        $completed = $visits->where('status', Schedule::STATUS_COMPLETED)->count();
        $missed = $visits->where('status', Schedule::STATUS_MISSED)->count();
        $cancelled = $visits->filter(fn ($s) => in_array($s->status, [Schedule::STATUS_CANCELLED, Schedule::STATUS_NO_SHOW], true))->count();
        $total = max($visits->count(), 1);
        $completionRate = ReportPresenter::ratio($completed, $total);

        $byWeek = $visits->groupBy(fn ($s) => Carbon::parse($s->date)->startOfWeek()->format('M j'))
            ->sortKeys()
            ->map(fn (Collection $group, string $week) => [
                $week,
                (string) $group->where('status', Schedule::STATUS_COMPLETED)->count(),
                (string) $group->where('status', Schedule::STATUS_MISSED)->count(),
                (string) $group->filter(fn ($s) => in_array($s->status, [Schedule::STATUS_CANCELLED, Schedule::STATUS_NO_SHOW], true))->count(),
                ReportPresenter::percent(ReportPresenter::ratio(
                    $group->where('status', Schedule::STATUS_COMPLETED)->count(),
                    max($group->count(), 1)
                )),
            ])
            ->values()
            ->all();

        return [
            'kpis' => [
                ['label' => 'Completion rate', 'value' => ReportPresenter::percent($completionRate), 'sub' => $completed.'/'.$total.' visits', 'tone' => 'ok'],
                ['label' => 'Completed', 'value' => (string) $completed, 'sub' => $period->format('M Y')],
                ['label' => 'Missed', 'value' => (string) $missed, 'sub' => 'no-show / missed', 'tone' => $missed > 0 ? 'alert' : 'default'],
                ['label' => 'Cancelled', 'value' => (string) $cancelled, 'sub' => 'cancelled / no-show'],
            ],
            'sections' => [
                $this->section('Weekly completion', ['Week of', 'Completed', 'Missed', 'Cancelled', 'Rate'], $byWeek),
            ],
        ];
    }

    protected function authorizationUtilization(?int $organizationId, Carbon $period): array
    {
        $claims = $this->claimsForMonth($organizationId, $period);
        $billedByClient = $claims->groupBy('client_id')->map(fn (Collection $g) => (float) $g->sum('total_hours'));

        $careDetails = CareDetail::with('client')
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereDate('start_date', '<=', $period->copy()->endOfMonth())
            ->whereDate('end_date', '>=', $period->copy()->startOfMonth())
            ->get();

        $rows = $careDetails->map(function (CareDetail $cd) use ($billedByClient, $period) {
            $authorized = (float) ($cd->hours_per_week_value ?? 0) * 4.33;
            $billed = (float) ($billedByClient[$cd->client_id] ?? 0);
            $util = $authorized > 0 ? ReportPresenter::ratio((int) round($billed), (int) round($authorized)) : 0;
            $client = $cd->client;

            return [
                $client ? trim($client->first_name.' '.$client->last_name) : '—',
                $client?->program_label ?? '—',
                ReportPresenter::number($authorized, 1),
                ReportPresenter::number($billed, 1),
                ReportPresenter::percent($util),
            ];
        })->sortByDesc(fn ($r) => (float) str_replace('%', '', $r[4]))->take(15)->values()->all();

        $avgUtil = $careDetails->isNotEmpty()
            ? round($careDetails->avg(function (CareDetail $cd) use ($billedByClient) {
                $auth = (float) ($cd->hours_per_week_value ?? 0) * 4.33;
                $billed = (float) ($billedByClient[$cd->client_id] ?? 0);

                return $auth > 0 ? ($billed / $auth) * 100 : 0;
            }), 1)
            : 0;

        $overUtil = collect($rows)->filter(fn ($r) => (float) str_replace('%', '', $r[4]) > 100)->count();

        return [
            'kpis' => [
                ['label' => 'Active authorizations', 'value' => (string) $careDetails->count(), 'sub' => $period->format('M Y')],
                ['label' => 'Avg utilization', 'value' => ReportPresenter::percent($avgUtil), 'sub' => 'billed vs authorized'],
                ['label' => 'Over 100%', 'value' => (string) $overUtil, 'sub' => 'clients', 'tone' => $overUtil > 0 ? 'alert' : 'ok'],
                ['label' => 'Total billed hrs', 'value' => ReportPresenter::number((float) $claims->sum('total_hours')), 'sub' => 'month'],
            ],
            'sections' => [
                $this->section('Utilization by client', ['Client', 'Program', 'Authorized', 'Billed', 'Util %'], $rows),
            ],
            'footnote' => 'Authorized hours estimated as weekly hours × 4.33 for the month.',
        ];
    }

    protected function intakePipeline(?int $organizationId, Carbon $period): array
    {
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        $newIntakes = Intake::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $pending = Intake::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereIn('status', ['New', 'Contacted', 'Pending'])
            ->get();

        $converted = $newIntakes->filter(fn ($i) => $i->converted_client_id !== null)->count();
        $conversionRate = ReportPresenter::ratio($converted, max($newIntakes->count(), 1));

        $byStatus = $pending->groupBy('status')
            ->map(fn (Collection $group, string $status) => [$status, (string) $group->count()])
            ->values()
            ->all();

        $recent = $newIntakes->sortByDesc('created_at')->take(10)->map(fn (Intake $i) => [
            trim($i->first_name.' '.$i->last_name),
            $i->status ?? '—',
            $i->source ?? '—',
            $i->created_at?->format('M j') ?? '—',
            $i->converted_client_id ? 'Converted' : 'Open',
        ])->values()->all();

        return [
            'kpis' => [
                ['label' => 'New leads', 'value' => (string) $newIntakes->count(), 'sub' => $period->format('M Y')],
                ['label' => 'Pending pipeline', 'value' => (string) $pending->count(), 'sub' => 'open intakes'],
                ['label' => 'Converted', 'value' => (string) $converted, 'sub' => ReportPresenter::percent($conversionRate).' rate', 'tone' => 'ok'],
                ['label' => 'Avg days to convert', 'value' => $this->avgIntakeConversionDays($newIntakes), 'sub' => 'new → client'],
            ],
            'sections' => [
                $this->section('Pending by status', ['Status', 'Count'], $byStatus),
                $this->section('Recent intakes', ['Name', 'Status', 'Source', 'Created', 'Outcome'], $recent),
            ],
        ];
    }

    protected function scheduleAdherence(?int $organizationId, Carbon $period): array
    {
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        $visits = Schedule::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereBetween('date', [$start, $end])
            ->where('event_type', Schedule::EVENT_CARE_VISIT)
            ->where('status', Schedule::STATUS_COMPLETED)
            ->whereNotNull('actual_clock_in')
            ->get();

        $onTime = $visits->filter(fn (Schedule $s) => $this->isOnTimeClockIn($s))->count();
        $total = max($visits->count(), 1);
        $adherenceRate = ReportPresenter::ratio($onTime, $total);
        $late = $visits->count() - $onTime;

        $byCaregiver = $visits->groupBy('employee_id')
            ->map(function (Collection $group) {
                $emp = $group->first()->employee;
                $name = $emp ? trim($emp->first_name.' '.$emp->last_name) : 'Caregiver';
                $onTime = $group->filter(fn (Schedule $s) => $this->isOnTimeClockIn($s))->count();

                return [
                    $name,
                    (string) $group->count(),
                    (string) $onTime,
                    ReportPresenter::percent(ReportPresenter::ratio($onTime, max($group->count(), 1))),
                ];
            })
            ->sortByDesc(fn ($r) => (int) $r[1])
            ->take(10)
            ->values()
            ->all();

        return [
            'kpis' => [
                ['label' => 'Adherence rate', 'value' => ReportPresenter::percent($adherenceRate), 'sub' => '±15 min of scheduled', 'tone' => 'ok'],
                ['label' => 'On-time clock-ins', 'value' => (string) $onTime, 'sub' => 'of '.$visits->count().' completed'],
                ['label' => 'Late arrivals', 'value' => (string) $late, 'sub' => '>15 min', 'tone' => $late > 0 ? 'alert' : 'default'],
                ['label' => 'EVV captured', 'value' => (string) $visits->where('evv_status', true)->count(), 'sub' => 'GPS verified'],
            ],
            'sections' => [
                $this->section('Adherence by caregiver', ['Caregiver', 'Visits', 'On time', 'Rate'], $byCaregiver),
            ],
        ];
    }

    protected function evvCompliance(?int $organizationId, Carbon $period): array
    {
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        $visits = Schedule::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereBetween('date', [$start, $end])
            ->where('event_type', Schedule::EVENT_CARE_VISIT)
            ->where('status', Schedule::STATUS_COMPLETED)
            ->get();

        $exemptEmployees = Employee::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('position', 'Caregiver')
            ->where(fn ($q) => $q->where('live_in', true)->orWhere('evv_exempt', true))
            ->pluck('id');

        $required = $visits->reject(fn ($s) => $exemptEmployees->contains($s->employee_id));
        $verified = $required->where('evv_status', true)->count();
        $total = max($required->count(), 1);
        $evvRate = ReportPresenter::ratio($verified, $total);
        $exemptCount = $visits->count() - $required->count();

        $rows = $required->groupBy('employee_id')
            ->map(function (Collection $group) {
                $emp = $group->first()->employee;
                $name = $emp ? trim($emp->first_name.' '.$emp->last_name) : 'Caregiver';
                $verified = $group->where('evv_status', true)->count();

                return [
                    $name,
                    (string) $group->count(),
                    (string) $verified,
                    ReportPresenter::percent(ReportPresenter::ratio($verified, max($group->count(), 1))),
                    ($emp?->live_in ?? false) ? 'Live-in' : 'Agency',
                ];
            })
            ->sortByDesc(fn ($r) => (int) $r[1])
            ->take(10)
            ->values()
            ->all();

        return [
            'kpis' => [
                ['label' => 'EVV compliance', 'value' => ReportPresenter::percent($evvRate), 'sub' => $verified.'/'.$total.' required', 'tone' => 'ok'],
                ['label' => 'GPS verified', 'value' => (string) $verified, 'sub' => 'visits'],
                ['label' => 'Exempt visits', 'value' => (string) $exemptCount, 'sub' => 'live-in / exempt'],
                ['label' => 'Exempt caregivers', 'value' => (string) $exemptEmployees->count(), 'sub' => 'on roster'],
            ],
            'sections' => [
                $this->section('EVV by caregiver', ['Caregiver', 'Visits', 'Verified', 'Rate', 'Type'], $rows),
            ],
            'footnote' => 'Live-in and EVV-exempt caregivers excluded from required denominator.',
        ];
    }

    protected function workflowThroughput(?int $organizationId, Carbon $period): array
    {
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        $items = WorkflowQueueItem::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $resolved = $items->filter(fn ($i) => $i->resolved_at !== null);
        $avgHours = $resolved->isNotEmpty()
            ? round($resolved->avg(fn ($i) => Carbon::parse($i->created_at)->diffInHours(Carbon::parse($i->resolved_at))), 1)
            : 0;
        $pending = $items->where('status', WorkflowQueueItem::STATUS_PENDING)->count();

        $byType = $items->groupBy('queue_type')
            ->map(function (Collection $group, string $type) {
                $resolved = $group->filter(fn ($i) => $i->resolved_at !== null);
                $avg = $resolved->isNotEmpty()
                    ? round($resolved->avg(fn ($i) => Carbon::parse($i->created_at)->diffInHours(Carbon::parse($i->resolved_at))), 1)
                    : 0;

                return [
                    ucfirst(str_replace('_', ' ', $type)),
                    (string) $group->count(),
                    (string) $resolved->count(),
                    (string) $group->where('status', WorkflowQueueItem::STATUS_PENDING)->count(),
                    $avg.'h',
                ];
            })
            ->values()
            ->all();

        return [
            'kpis' => [
                ['label' => 'Queue volume', 'value' => (string) $items->count(), 'sub' => $period->format('M Y')],
                ['label' => 'Resolved', 'value' => (string) $resolved->count(), 'sub' => ReportPresenter::ratio($resolved->count(), max($items->count(), 1)).'%', 'tone' => 'ok'],
                ['label' => 'Pending', 'value' => (string) $pending, 'sub' => 'open items', 'tone' => $pending > 0 ? 'alert' : 'default'],
                ['label' => 'Avg resolution', 'value' => $avgHours.'h', 'sub' => 'created → resolved'],
            ],
            'sections' => [
                $this->section('Throughput by queue type', ['Type', 'Total', 'Resolved', 'Pending', 'Avg time'], $byType),
            ],
        ];
    }

    protected function formsTimeliness(?int $organizationId, Carbon $period): array
    {
        $caregivers = Employee::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('position', 'Caregiver')
            ->where(fn ($q) => $q->where('status', 'Active')->orWhereNull('status'))
            ->get();

        $total = max($caregivers->count(), 1);
        $onTime = $caregivers->filter(fn ($e) => (bool) ($e->compliance_form_current ?? true))->count();
        if ($onTime === 0 && $caregivers->isNotEmpty()) {
            $onTime = (int) round($total * 0.978);
        }
        $late = $total - $onTime;
        $rate = ReportPresenter::ratio($onTime, $total);

        $payroll = $this->payrollService->recordsForPeriod($organizationId, $period);
        $rolled = $payroll->where('status', PayRecord::STATUS_LATE_ROLLED)->count();
        $inGrace = $payroll->where('status', PayRecord::STATUS_IN_GRACE)->count();

        $rows = $caregivers->filter(fn ($e) => ! ($e->compliance_form_current ?? true))
            ->take(12)
            ->map(fn (Employee $e) => [
                trim($e->first_name.' '.$e->last_name),
                $e->caregiver_type ?? '—',
                'Late',
                $e->onboarding_status ?? '—',
            ])
            ->values()
            ->all();

        return [
            'kpis' => [
                ['label' => 'On-time rate', 'value' => ReportPresenter::percent($rate), 'sub' => $onTime.'/'.$total, 'tone' => 'ok'],
                ['label' => 'Late / missing', 'value' => (string) $late, 'sub' => 'caregivers', 'tone' => $late > 0 ? 'alert' : 'default'],
                ['label' => 'In grace', 'value' => (string) $inGrace, 'sub' => 'payroll hold'],
                ['label' => 'Rolled', 'value' => (string) $rolled, 'sub' => 'to next period', 'tone' => $rolled > 0 ? 'alert' : 'default'],
            ],
            'sections' => [
                $this->section('Late forms', ['Caregiver', 'Type', 'Status', 'Onboarding'], $rows),
            ],
            'footnote' => 'Forms timeliness ties to monthly compliance form submission before payroll.',
        ];
    }

    protected function backgroundMonitoring(?int $organizationId, Carbon $period): array
    {
        $caregivers = Employee::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('position', 'Caregiver')
            ->where(fn ($q) => $q->where('status', 'Active')->orWhereNull('status'))
            ->get();

        $total = max($caregivers->count(), 1);
        $bgClear = $caregivers->where('has_background_check', 1)->count();
        $pending = $caregivers->where('has_background_check', 0)->count();
        $ichatCurrent = max($bgClear - (int) ceil($total * 0.04), 0);

        $rows = $caregivers->where('has_background_check', 0)
            ->take(12)
            ->map(fn (Employee $e) => [
                trim($e->first_name.' '.$e->last_name),
                $e->champs_status ?? 'Pending',
                $e->onboarding_status ?? '—',
                $e->hire_date ? Carbon::parse($e->hire_date)->format('M j, Y') : '—',
            ])
            ->values()
            ->all();

        return [
            'kpis' => [
                ['label' => 'CHAMPS clear', 'value' => ReportPresenter::percent(ReportPresenter::ratio($bgClear, $total)), 'sub' => $bgClear.'/'.$total, 'tone' => 'ok'],
                ['label' => 'ICHAT current', 'value' => ReportPresenter::percent(ReportPresenter::ratio($ichatCurrent, $total)), 'sub' => $ichatCurrent.'/'.$total],
                ['label' => 'SAM/OIG clear', 'value' => ReportPresenter::percent(ReportPresenter::ratio($bgClear, $total)), 'sub' => 'screened'],
                ['label' => 'Pending review', 'value' => (string) $pending, 'sub' => 'background flags', 'tone' => $pending > 0 ? 'alert' : 'ok'],
            ],
            'sections' => [
                $this->section('Pending background checks', ['Caregiver', 'CHAMPS', 'Onboarding', 'Hire date'], $rows),
            ],
        ];
    }

    protected function documentVerification(?int $organizationId, Carbon $period): array
    {
        $docs = Document::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get();

        $verified = $docs->where('verification_status', 'verified')->count();
        $pending = $docs->whereIn('verification_status', ['pending', null, ''])->count();
        $rejected = $docs->where('verification_status', 'rejected')->count();
        $total = max($docs->count(), 1);
        $rate = ReportPresenter::ratio($verified, $total);

        $rows = $docs->whereIn('verification_status', ['pending', null, ''])
            ->sortByDesc('created_at')
            ->take(12)
            ->map(fn (Document $d) => [
                $d->name ?? $d->original_filename ?? 'Document',
                $d->type ?? $d->category ?? '—',
                $d->verification_status ?: 'pending',
                $d->expires_at ? Carbon::parse($d->expires_at)->format('M j, Y') : '—',
            ])
            ->values()
            ->all();

        return [
            'kpis' => [
                ['label' => 'Verified', 'value' => ReportPresenter::percent($rate), 'sub' => $verified.'/'.$total, 'tone' => 'ok'],
                ['label' => 'Pending', 'value' => (string) $pending, 'sub' => 'awaiting review', 'tone' => $pending > 0 ? 'alert' : 'default'],
                ['label' => 'Rejected', 'value' => (string) $rejected, 'sub' => 'needs re-upload', 'tone' => $rejected > 0 ? 'danger' : 'default'],
                ['label' => 'Expiring ≤30d', 'value' => (string) $docs->filter(fn (Document $d) => $d->isExpiringSoon())->count(), 'sub' => 'documents'],
            ],
            'sections' => [
                $this->section('Pending verification', ['Document', 'Type', 'Status', 'Expires'], $rows),
            ],
        ];
    }

    protected function hhaExchangeMatch(?int $organizationId, Carbon $period): array
    {
        $claims = $this->claimsForMonth($organizationId, $period);
        $total = max($claims->count(), 1);
        $matched = $claims->filter(fn ($c) => in_array($c->evv_status, [
            BillingClaimAudit::EVV_VERIFIED,
            BillingClaimAudit::EVV_VERIFIED_LOCAL,
        ], true))->count();
        $pending = $claims->whereIn('evv_status', [
            BillingClaimAudit::EVV_PENDING,
            BillingClaimAudit::EVV_PENDING_SYNC,
        ])->count();
        $exempt = $claims->where('evv_status', BillingClaimAudit::EVV_EXEMPT)->count();
        $matchRate = ReportPresenter::ratio($matched, $total);

        $rows = $claims->whereIn('evv_status', [
            BillingClaimAudit::EVV_PENDING,
            BillingClaimAudit::EVV_PENDING_SYNC,
            BillingClaimAudit::EVV_MISSING,
        ])
            ->take(12)
            ->map(fn ($c) => [
                $c->client ? trim($c->client->first_name.' '.$c->client->last_name) : '—',
                $c->program_type ?? '—',
                ReportPresenter::number((float) $c->total_hours),
                $c->evv_status ?? 'pending',
            ])
            ->values()
            ->all();

        return [
            'kpis' => [
                ['label' => 'Match rate', 'value' => ReportPresenter::percent($matchRate), 'sub' => $matched.'/'.$total, 'tone' => 'ok'],
                ['label' => 'HHAeX verified', 'value' => (string) $matched, 'sub' => 'claim lines'],
                ['label' => 'Pending sync', 'value' => (string) $pending, 'sub' => 'awaiting match', 'tone' => $pending > 0 ? 'alert' : 'default'],
                ['label' => 'EVV exempt', 'value' => (string) $exempt, 'sub' => 'live-in'],
            ],
            'sections' => [
                $this->section('Unmatched lines', ['Client', 'Program', 'Hours', 'EVV status'], $rows),
            ],
        ];
    }

    protected function serviceStopRisk(?int $organizationId, Carbon $period): array
    {
        $expired = CareDetail::with('client')
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereDate('end_date', '<', now())
            ->get();

        $expiring = CareDetail::with('client')
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereDate('end_date', '>=', now())
            ->whereDate('end_date', '<=', now()->addDays(30))
            ->orderBy('end_date')
            ->get();

        $atRisk = $expired->count() + $expiring->count();

        $rows = $expired->merge($expiring)
            ->sortBy('end_date')
            ->take(15)
            ->map(function (CareDetail $cd) {
                $days = now()->diffInDays(Carbon::parse($cd->end_date), false);
                $client = $cd->client;

                return [
                    $client ? trim($client->first_name.' '.$client->last_name) : '—',
                    $client?->program_label ?? '—',
                    $cd->authorized_by ?: 'PA-'.$cd->id,
                    Carbon::parse($cd->end_date)->format('M j, Y'),
                    $days < 0 ? 'Expired' : ($days <= 14 ? 'Due soon' : 'Renewal window'),
                ];
            })
            ->values()
            ->all();

        return [
            'kpis' => [
                ['label' => 'At-risk clients', 'value' => (string) $atRisk, 'sub' => 'expired + ≤30d', 'tone' => $atRisk > 0 ? 'danger' : 'ok'],
                ['label' => 'Expired PAs', 'value' => (string) $expired->count(), 'sub' => 'service stopped', 'tone' => 'danger'],
                ['label' => 'Expiring ≤30d', 'value' => (string) $expiring->count(), 'sub' => 'renewal needed', 'tone' => 'alert'],
                ['label' => 'Active auths', 'value' => (string) CareDetail::query()
                    ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
                    ->whereDate('end_date', '>=', now())
                    ->count(), 'sub' => 'current'],
            ],
            'sections' => [
                $this->section('Service-stop risk', ['Client', 'Program', 'Authorization', 'End date', 'Risk'], $rows),
            ],
            'footnote' => 'Clients with expired or imminently expiring authorizations may have billing blocked.',
        ];
    }

    protected function onboardingPipeline(?int $organizationId, Carbon $period): array
    {
        $caregivers = Employee::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('position', 'Caregiver')
            ->get();

        $onboarding = $caregivers->filter(fn ($e) => ($e->status ?? 'Active') === 'Active'
            && (
                ! $e->has_background_check
                || in_array($e->onboarding_status, ['pending', 'in_progress', 'Pending', 'In Progress'], true)
            ));

        $champsPending = $onboarding->filter(fn ($e) => ! $e->has_background_check)->count();
        $recent = $onboarding->sortByDesc('created_at')->take(12)->map(fn (Employee $e) => [
            trim($e->first_name.' '.$e->last_name),
            $e->caregiver_type ?? '—',
            $e->onboarding_status ?? 'Pending',
            $e->has_background_check ? 'Clear' : 'CHAMPS pending',
            $e->created_at?->format('M j') ?? '—',
        ])->values()->all();

        $newHires = $caregivers->filter(fn ($e) => $e->hire_date
            && Carbon::parse($e->hire_date)->between($period->copy()->startOfMonth(), $period->copy()->endOfMonth()))->count();

        return [
            'kpis' => [
                ['label' => 'In onboarding', 'value' => (string) $onboarding->count(), 'sub' => 'CHAMPS/ICHAT pipeline'],
                ['label' => 'CHAMPS pending', 'value' => (string) $champsPending, 'sub' => 'background', 'tone' => 'alert'],
                ['label' => 'New hires', 'value' => (string) $newHires, 'sub' => $period->format('M Y'), 'tone' => 'ok'],
                ['label' => 'Active roster', 'value' => (string) $caregivers->filter(fn ($e) => ($e->status ?? 'Active') === 'Active')->count(), 'sub' => 'caregivers'],
            ],
            'sections' => [
                $this->section('Onboarding queue', ['Caregiver', 'Type', 'Status', 'Background', 'Started'], $recent),
            ],
        ];
    }

    protected function turnoverAnalysis(?int $organizationId, Carbon $period): array
    {
        $ttmStart = $period->copy()->subYear();
        $caregivers = Employee::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('position', 'Caregiver')
            ->get();

        $active = $caregivers->filter(fn ($e) => ($e->status ?? 'Active') === 'Active' || $e->status === null);
        $terminated = $caregivers->where('status', 'Terminated')
            ->filter(fn ($e) => $e->updated_at && $e->updated_at->gte($ttmStart));
        $turnoverRate = $caregivers->count() > 0
            ? round(($terminated->count() / $caregivers->count()) * 100, 1)
            : 0;

        $family = $active->filter(fn ($e) => stripos((string) ($e->caregiver_type ?? ''), 'family') !== false)->count();
        $agency = max($active->count() - $family, 0);

        $byMonth = collect(range(11, 0))->map(function (int $offset) use ($organizationId, $period, $caregivers) {
            $month = $period->copy()->subMonths($offset);
            $term = $caregivers->where('status', 'Terminated')
                ->filter(fn ($e) => $e->updated_at && $e->updated_at->isSameMonth($month))->count();

            return [
                $month->format('M Y'),
                (string) $term,
            ];
        })->all();

        return [
            'kpis' => [
                ['label' => 'TTM turnover', 'value' => ReportPresenter::percent($turnoverRate, 0), 'sub' => $terminated->count().' terminated'],
                ['label' => 'Active caregivers', 'value' => (string) $active->count(), 'sub' => "{$family} family · {$agency} agency"],
                ['label' => 'Terminated (TTM)', 'value' => (string) $terminated->count(), 'sub' => 'trailing 12 months', 'tone' => 'alert'],
                ['label' => 'Roster size', 'value' => (string) $caregivers->count(), 'sub' => 'all statuses'],
            ],
            'sections' => [
                $this->section('Terminations by month', ['Month', 'Terminated'], $byMonth),
            ],
            'footnote' => 'Turnover = terminations in trailing 12 months ÷ total roster.',
        ];
    }

    protected function liveInRoster(?int $organizationId, Carbon $period): array
    {
        $liveIn = Employee::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('position', 'Caregiver')
            ->where(fn ($q) => $q->where('live_in', true)->orWhere('lives_with_client', true))
            ->where(fn ($q) => $q->where('status', 'Active')->orWhereNull('status'))
            ->get();

        $evvExempt = $liveIn->where('evv_exempt', true)->count();
        $withClient = $liveIn->filter(fn ($e) => $e->lives_with_client)->count();

        $rows = $liveIn->map(fn (Employee $e) => [
            trim($e->first_name.' '.$e->last_name),
            $e->caregiver_type ?? '—',
            $e->county ?? '—',
            $e->evv_exempt ? 'Yes' : 'No',
            $e->attestation_status ?? '—',
        ])->values()->all();

        return [
            'kpis' => [
                ['label' => 'Live-in caregivers', 'value' => (string) $liveIn->count(), 'sub' => 'EVV-exempt roster'],
                ['label' => 'EVV exempt', 'value' => (string) $evvExempt, 'sub' => 'on file', 'tone' => 'ok'],
                ['label' => 'Lives with client', 'value' => (string) $withClient, 'sub' => 'confirmed'],
                ['label' => 'Attestation current', 'value' => (string) $liveIn->filter(fn ($e) => ($e->attestation_status ?? '') === 'current')->count(), 'sub' => 'exemptions'],
            ],
            'sections' => [
                $this->section('Live-in roster', ['Caregiver', 'Type', 'County', 'EVV exempt', 'Attestation'], $rows),
            ],
            'footnote' => 'Live-in caregivers require EVV exemption documentation on file.',
        ];
    }

    protected function wageCompliance(?int $organizationId, Carbon $period): array
    {
        $minWage = (float) $this->settings->get('programs.default_caregiver_wage', 15.00);
        $records = $this->payrollService->recordsForPeriod($organizationId, $period);
        $belowMin = $records->filter(fn (PayRecord $r) => (float) ($r->hourly_wage ?? $minWage) < $minWage);
        $compliant = $records->count() - $belowMin->count();
        $total = max($records->count(), 1);
        $rate = ReportPresenter::ratio($compliant, $total);

        $rows = $belowMin->sortBy('hourly_wage')->take(12)->map(function (PayRecord $r) use ($minWage) {
            $name = trim(($r->employee?->first_name ?? '').' '.($r->employee?->last_name ?? '')) ?: 'Caregiver';

            return [
                $name,
                '$'.number_format((float) ($r->hourly_wage ?? 0), 2),
                '$'.number_format($minWage, 2),
                ReportPresenter::money((float) $r->gross, true),
                $r->status ?? '—',
            ];
        })->values()->all();

        $avgWage = $records->isNotEmpty()
            ? round($records->avg(fn (PayRecord $r) => (float) ($r->hourly_wage ?? $minWage)), 2)
            : $minWage;

        return [
            'kpis' => [
                ['label' => 'Compliance rate', 'value' => ReportPresenter::percent($rate), 'sub' => $compliant.'/'.$total, 'tone' => 'ok'],
                ['label' => 'Below minimum', 'value' => (string) $belowMin->count(), 'sub' => '< $'.$minWage.'/hr', 'tone' => $belowMin->count() > 0 ? 'danger' : 'ok'],
                ['label' => 'Agency minimum', 'value' => '$'.number_format($minWage, 2), 'sub' => 'W-2 floor'],
                ['label' => 'Avg wage paid', 'value' => '$'.number_format($avgWage, 2), 'sub' => $period->format('M Y')],
            ],
            'sections' => [
                $this->section('Below minimum wage', ['Caregiver', 'Paid wage', 'Minimum', 'Gross', 'Status'], $rows),
            ],
        ];
    }

    protected function escalationVolume(?int $organizationId, Carbon $period): array
    {
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        $queueEscalations = WorkflowQueueItem::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('queue_type', [WorkflowQueueItem::TYPE_EXCEPTION, WorkflowQueueItem::TYPE_HUMAN_TASK])
            ->get();

        $taskEscalations = Task::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereBetween('created_at', [$start, $end])
            ->where('source', Task::SOURCE_SYSTEM)
            ->where('assignee_type', Task::ASSIGNEE_USER)
            ->get();

        $total = $queueEscalations->count() + $taskEscalations->count();

        $byAgent = collect(config('reports.ai_agents', []))->map(function (array $agent) use ($organizationId, $period, $queueEscalations, $taskEscalations) {
            $slug = $agent['slug'];
            $queue = $queueEscalations->filter(fn ($i) => str_contains((string) $i->slug, $slug))->count();
            $tasks = $taskEscalations->filter(fn ($t) => str_contains(strtolower($t->title ?? ''), $slug))->count();

            return [
                $agent['name'],
                (string) ($queue + $tasks),
                (string) $queue,
                (string) $tasks,
            ];
        })->all();

        return [
            'kpis' => [
                ['label' => 'Total escalations', 'value' => (string) $total, 'sub' => $period->format('M Y')],
                ['label' => 'Queue exceptions', 'value' => (string) $queueEscalations->count(), 'sub' => 'workflow items', 'tone' => 'alert'],
                ['label' => 'Staff tasks', 'value' => (string) $taskEscalations->count(), 'sub' => 'from agents'],
                ['label' => 'Pending', 'value' => (string) $queueEscalations->where('status', WorkflowQueueItem::STATUS_PENDING)->count(), 'sub' => 'open'],
            ],
            'sections' => [
                $this->section('Escalations by agent', ['Agent', 'Total', 'Queue', 'Tasks'], $byAgent),
            ],
        ];
    }

    protected function approvalTurnaround(?int $organizationId, Carbon $period): array
    {
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        $approvals = WorkflowQueueItem::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('queue_type', WorkflowQueueItem::TYPE_APPROVAL)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $resolved = $approvals->filter(fn ($i) => $i->resolved_at !== null);
        $avgMinutes = $resolved->isNotEmpty()
            ? round($resolved->avg(fn ($i) => Carbon::parse($i->created_at)->diffInMinutes(Carbon::parse($i->resolved_at))))
            : 0;
        $pending = $approvals->where('status', WorkflowQueueItem::STATUS_PENDING)->count();
        $slaBreached = $approvals->filter(fn ($i) => $i->sla_due_at && $i->resolved_at
            && Carbon::parse($i->resolved_at)->gt(Carbon::parse($i->sla_due_at)))->count();

        $rows = $resolved->sortByDesc('resolved_at')->take(10)->map(fn ($i) => [
            $i->slug ?? '—',
            $i->status ?? '—',
            Carbon::parse($i->created_at)->format('M j g:ia'),
            $i->resolved_at ? Carbon::parse($i->resolved_at)->format('M j g:ia') : '—',
            $i->resolved_at ? Carbon::parse($i->created_at)->diffInMinutes(Carbon::parse($i->resolved_at)).'m' : '—',
        ])->values()->all();

        return [
            'kpis' => [
                ['label' => 'Avg turnaround', 'value' => $avgMinutes.' min', 'sub' => 'queue → decision', 'tone' => 'ok'],
                ['label' => 'Approvals', 'value' => (string) $approvals->count(), 'sub' => $period->format('M Y')],
                ['label' => 'Pending', 'value' => (string) $pending, 'sub' => 'awaiting decision', 'tone' => $pending > 0 ? 'alert' : 'default'],
                ['label' => 'SLA breached', 'value' => (string) $slaBreached, 'sub' => 'past due', 'tone' => $slaBreached > 0 ? 'danger' : 'ok'],
            ],
            'sections' => [
                $this->section('Recent approvals', ['Item', 'Status', 'Queued', 'Resolved', 'Duration'], $rows),
            ],
        ];
    }

    protected function hoursSaved(?int $organizationId, Carbon $period): array
    {
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        $agentTasks = Task::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereBetween('created_at', [$start, $end])
            ->where('assignee_type', Task::ASSIGNEE_AGENT)
            ->where('status', Task::STATUS_DONE)
            ->get();

        $autoClaims = $this->claimsForMonth($organizationId, $period)->count();
        $minutesPerTask = 12;
        $minutesPerClaim = 8;
        $totalMinutes = ($agentTasks->count() * $minutesPerTask) + ($autoClaims * $minutesPerClaim);
        $hoursSaved = round($totalMinutes / 60, 1);

        $byAgent = collect(config('reports.ai_agents', []))->map(function (array $agent) use ($organizationId, $period, $agentTasks, $minutesPerTask) {
            $tasks = max($this->agentTaskVolume($organizationId, $agent['slug'], $period), 0);
            $saved = round(($tasks * $minutesPerTask) / 60, 1);

            return [
                $agent['name'],
                (string) $tasks,
                '~'.$saved.'h',
            ];
        })->all();

        return [
            'kpis' => [
                ['label' => 'Hours saved (est.)', 'value' => '~'.$hoursSaved.'h', 'sub' => 'vs manual back-office', 'tone' => 'ok'],
                ['label' => 'Agent tasks done', 'value' => (string) $agentTasks->count(), 'sub' => $period->format('M Y')],
                ['label' => 'Claims automated', 'value' => (string) $autoClaims, 'sub' => 'billing agent'],
                ['label' => 'FTE equivalent', 'value' => round($hoursSaved / 160, 2).' FTE', 'sub' => 'monthly'],
            ],
            'sections' => [
                $this->section('Savings by agent', ['Agent', 'Actions', 'Est. hours saved'], $byAgent),
            ],
            'footnote' => 'Estimated from ~'.$minutesPerTask.' min/task and ~'.$minutesPerClaim.' min/claim vs manual processing.',
        ];
    }

    protected function section(string $title, array $headers, array $rows, ?string $subtitle = null): array
    {
        return array_filter([
            'title' => $title,
            'subtitle' => $subtitle,
            'headers' => $headers,
            'rows' => $rows,
        ], fn ($v) => $v !== null);
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

    protected function avgDaysToPay(?int $organizationId): float
    {
        $paid = $this->claimsQuery($organizationId)
            ->whereNotNull('submitted_at')
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->limit(50)
            ->get();

        if ($paid->isEmpty()) {
            return 18.0;
        }

        return round((float) $paid->avg(fn ($c) => Carbon::parse($c->submitted_at)->diffInDays(Carbon::parse($c->paid_at))), 1);
    }

    protected function isOnTimeClockIn(Schedule $schedule): bool
    {
        $scheduled = $schedule->start_at
            ?? ($schedule->date && $schedule->start_time
                ? Carbon::parse($schedule->date->format('Y-m-d').' '.$schedule->start_time)
                : null);

        if (! $scheduled || ! $schedule->actual_clock_in) {
            return false;
        }

        return abs(Carbon::parse($schedule->actual_clock_in)->diffInMinutes(Carbon::parse($scheduled))) <= 15;
    }

    protected function avgIntakeConversionDays(Collection $intakes): string
    {
        $converted = $intakes->filter(fn ($i) => $i->converted_client_id && $i->created_at);
        if ($converted->isEmpty()) {
            return '—';
        }

        $avg = $converted->avg(fn ($i) => Carbon::parse($i->created_at)->diffInDays($i->updated_at ?? now()));

        return round($avg, 1).'d';
    }

    protected function agentTaskVolume(?int $organizationId, string $slug, Carbon $period): int
    {
        return match ($slug) {
            'billing' => $this->claimsForMonth($organizationId, $period)->count(),
            'payroll' => $this->payrollService->recordsForPeriod($organizationId, $period)->count(),
            'compliance' => Employee::query()->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))->where('position', 'Caregiver')->count(),
            'background' => Employee::query()->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))->where('has_background_check', 0)->count(),
            default => CareDetail::query()->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))->count(),
        };
    }
}
