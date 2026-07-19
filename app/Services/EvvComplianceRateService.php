<?php

namespace App\Services;

use App\Models\PayRecord;
use App\Models\Schedule;
use Carbon\Carbon;

/**
 * EVV weekly-consistency compliance rate — a COACHING SIGNAL ONLY. It is
 * display-only and NEVER withholds, blocks, delays, or changes pay.
 *
 * It measures how evenly a caregiver clocked their approved monthly hours across
 * the weeks of the month, rather than cramming them. Method:
 *
 *   1. Approved monthly hours are split across the month's calendar weeks in
 *      proportion to how many days of the month fall in each week (so partial
 *      weeks at the month's edges get a proportionally smaller share). This is a
 *      strict generalization of "approved ÷ weeks = weekly share" for real months.
 *   2. Each week can only credit clocked hours UP TO its own share — cramming a
 *      month's hours into one week does not carry the excess over.
 *   3. Rate = sum of weekly credits ÷ approved hours (0–100%).
 *
 * Even clocking → ~100%. All hours in one week → low %. A fully missed week
 * forfeits that week's share. One monthly %, surfaced on the payroll row as a
 * nudge that feeds the weekly EVV monitor. Clocked hours use the same clean-visit
 * gate as payable/payroll hours, so a flagged visit never inflates the signal.
 */
class EvvComplianceRateService
{
    public function __construct(
        protected VisitReportService $visits,
        protected PayrollHoursResolver $hoursResolver,
    ) {}

    /**
     * @return array{rate:int, approved:float, clocked:float, weeks:int, band:string}|null
     *   Returns null when the signal does not apply (live-in / EVV-exempt caregiver
     *   or DHS days-met pay, which have no clock stream to score) or when the
     *   inputs are missing (no approved monthly hours on file).
     */
    public function rateForRecord(PayRecord $record): ?array
    {
        $employee = $record->employee;
        $program = $this->hoursResolver->resolveProgramTag($record);

        // Only EVV-clocked programs have a weekly-consistency signal.
        if (! $this->hoursResolver->requiresEvvHours($employee, $program)) {
            return null;
        }

        $periodKey = $record->period_key ?? $this->hoursResolver->periodKeyFromLabel($record->period);

        if (! $periodKey || ! $record->employee_id) {
            return null;
        }

        $approved = $this->approvedHours($record);

        if ($approved === null || $approved <= 0) {
            return null;
        }

        $start = Carbon::createFromFormat('Y-m', $periodKey)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $daysInMonth = (int) $start->daysInMonth;

        // Proportional weekly shares: how many of the month's days fall in each
        // calendar week (Mon-anchored). Partial edge weeks get a smaller share.
        $weekDayCounts = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $weekKey = $day->copy()->startOfWeek()->toDateString();
            $weekDayCounts[$weekKey] = ($weekDayCounts[$weekKey] ?? 0) + 1;
        }

        // Clocked hours per week from clean, completed visits (identical gate to
        // VisitReportService::payableHours so the numerator matches payroll hours).
        $weekClocked = [];
        $clockedTotal = 0.0;

        $visits = Schedule::withoutGlobalScopes()
            ->where('organization_id', $record->organization_id)
            ->where('employee_id', $record->employee_id)
            ->when($record->client_id, fn ($q) => $q->where('client_id', $record->client_id))
            ->where('event_type', Schedule::EVENT_CARE_VISIT)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', [Schedule::STATUS_COMPLETED, 'Verified', 'completed'])
            ->with('client')
            ->get();

        foreach ($visits as $visit) {
            if (! $visit->date || ! $this->visits->hasCleanTimeData($visit)) {
                continue;
            }

            $hours = (float) ($this->visits->effectiveHours($visit) ?? 0);

            if ($hours <= 0) {
                continue;
            }

            $weekKey = $visit->date->copy()->startOfWeek()->toDateString();
            $weekClocked[$weekKey] = ($weekClocked[$weekKey] ?? 0) + $hours;
            $clockedTotal += $hours;
        }

        // Sum each week's credit (clocked, capped at that week's proportional share).
        $credit = 0.0;
        foreach ($weekDayCounts as $weekKey => $dayCount) {
            $share = $approved * ($dayCount / $daysInMonth);
            $credit += min($weekClocked[$weekKey] ?? 0.0, $share);
        }

        $rate = (int) round(min(100, max(0, $credit / $approved * 100)));

        return [
            'rate'     => $rate,
            'approved' => round($approved, 1),
            'clocked'  => round($clockedTotal, 1),
            'weeks'    => count($weekDayCounts),
            'band'     => $this->band($rate),
        ];
    }

    /**
     * The monthly target: the authorized plan-of-care hours where available, then
     * the reported delivered hours, then the record's own resolved payable hours.
     */
    protected function approvedHours(PayRecord $record): ?float
    {
        $form = $record->complianceForm ?? $this->hoursResolver->findComplianceForm($record);

        if ($form) {
            if ($form->authorized_hours !== null && (float) $form->authorized_hours > 0) {
                return (float) $form->authorized_hours;
            }

            if ($form->delivered_hours !== null && (float) $form->delivered_hours > 0) {
                return (float) $form->delivered_hours;
            }
        }

        return $record->hours !== null && (float) $record->hours > 0
            ? (float) $record->hours
            : null;
    }

    protected function band(int $rate): string
    {
        return match (true) {
            $rate >= 90 => 'good',
            $rate >= 70 => 'watch',
            default     => 'low',
        };
    }
}
