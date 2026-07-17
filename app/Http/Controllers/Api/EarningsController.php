<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCaregiver;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayRecord;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Earnings & hours for the Payroll screen: year-to-date totals, payroll
 * integration status, a per-period earnings series (bar graph) and a weekly
 * hours series (line graph).
 */
class EarningsController extends Controller
{
    use ResolvesCaregiver;

    public function summary(Request $request): JsonResponse
    {
        $caregiver = $this->caregiver();

        $year = (int) ($request->integer('year') ?: now()->year);
        $periods = min(max((int) $request->integer('periods', 6) ?: 6, 1), 24);
        $weeks = min(max((int) $request->integer('weeks', 8) ?: 8, 1), 26);

        $ytd = PayRecord::query()
            ->where('employee_id', $caregiver->id)
            ->where('period_key', 'like', $year.'-%')
            ->get(['hours', 'gross']);

        return response()->json([
            'data' => [
                'year' => $year,
                'year_to_date' => [
                    'gross' => round((float) $ytd->sum('gross'), 2),
                    'hours' => round((float) $ytd->sum('hours'), 2),
                    'paystub_count' => $ytd->count(),
                ],
                'integrations' => [
                    'quickbooks' => [
                        'label' => 'QuickBooks',
                        'connected' => filled(config('payroll.quickbooks_url')),
                    ],
                    'gusto' => [
                        'label' => 'Gusto',
                        'ready' => filled(config('payroll.gusto_url')),
                    ],
                ],
                'earnings_series' => $this->earningsSeries($caregiver, $periods),
                'hours_series' => $this->hoursSeries($caregiver, $weeks),
            ],
        ]);
    }

    /**
     * Most recent pay periods, oldest-first (bar graph of gross per period).
     *
     * @return array<int, array{period: string|null, period_key: string|null, gross: float, hours: float, status: string|null, paid_date: string|null}>
     */
    private function earningsSeries(Employee $caregiver, int $periods): array
    {
        return PayRecord::query()
            ->where('employee_id', $caregiver->id)
            ->orderByDesc('created_at')
            ->limit($periods)
            ->get(['period', 'period_key', 'hours', 'gross', 'status', 'paid_date'])
            ->reverse()
            ->map(fn (PayRecord $r) => [
                'period' => $r->period,
                'period_key' => $r->period_key,
                'gross' => round((float) $r->gross, 2),
                'hours' => round((float) $r->hours, 2),
                'status' => $r->status,
                'paid_date' => optional($r->paid_date)->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * Completed-visit hours per week for the last N weeks, oldest-first
     * (line graph of hours worked).
     *
     * @return array<int, array{week_start: string, week_end: string, label: string, hours: float}>
     */
    private function hoursSeries(Employee $caregiver, int $weeks): array
    {
        $start = now()->startOfWeek()->subWeeks($weeks - 1);

        $completed = $caregiver->schedules()
            ->where('status', Schedule::STATUS_COMPLETED)
            ->whereNotNull('start_at')
            ->where('start_at', '>=', $start)
            ->get(['start_at', 'total_hours']);

        $series = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->endOfWeek();

            $hours = $completed
                ->filter(fn ($s) => $s->start_at->betweenIncluded($weekStart, $weekEnd))
                ->sum('total_hours');

            $series[] = [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'label' => $weekStart->format('M j'),
                'hours' => round((float) $hours, 2),
            ];
        }

        return $series;
    }
}
