<?php

namespace App\Support;

use App\Models\Employee;

class CaregiverRegistryMetrics
{
    /**
     * @return array{
     *     status: string,
     *     checks_expiring: bool,
     *     checks_flagged: bool,
     *     compliance_missing: bool,
     *     program: string,
     *     type: string,
     *     live_in: bool
     * }
     */
    public static function rowFlags(Employee $caregiver): array
    {
        $checks = $caregiver->backgroundChecks;
        $flag = $checks->firstWhere('status', 'Flagged');
        $ichat = $checks->first(fn ($b) => $b->type === 'ICHAT'
            && $b->next_due
            && $b->next_due->lte(now()->addDays(30))
            && $b->next_due->gte(now()));
        $enroll = $checks->whereIn('status', ['Enrolling', 'Submitted'])->count();

        if ($flag) {
            $checkTone = 'flag';
        } elseif ($ichat) {
            $checkTone = 'due';
        } elseif ($caregiver->onboarding_status === 'Pending onboarding' || $enroll > 0) {
            $checkTone = 'progress';
        } else {
            $checkTone = 'clear';
        }

        $assignment = $caregiver->assignments->firstWhere('status', 'Active') ?? $caregiver->assignments->first();
        $dueForm = $caregiver->complianceForms->firstWhere('status', 'Due')
            ?? $caregiver->complianceForms->firstWhere('status', 'Awaiting');

        return [
            'status' => CaregiverStatus::normalize($caregiver),
            'checks_expiring' => $checkTone === 'due',
            'checks_flagged' => $checkTone === 'flag',
            'compliance_missing' => $caregiver->complianceForms
                ->where('period', now()->format('Y-m'))
                ->whereIn('status', ['Due', 'Awaiting'])
                ->isNotEmpty(),
            'program' => (string) ($assignment->program ?? 'MICH'),
            'type' => $caregiver->caregiver_type ?? 'Family',
            'live_in' => (bool) $caregiver->live_in,
        ];
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public static function filterCounts(iterable $rows): array
    {
        $counts = [
            'total' => 0,
            'active' => 0,
            'pending' => 0,
            'on_hold' => 0,
            'checks_expiring' => 0,
            'checks_flagged' => 0,
            'compliance_missing' => 0,
        ];

        foreach ($rows as $row) {
            $counts['total']++;

            match ($row['status'] ?? '') {
                'Active' => $counts['active']++,
                'Pending' => $counts['pending']++,
                'On Hold' => $counts['on_hold']++,
                default => null,
            };
            if ($row['checks_expiring'] ?? false) {
                $counts['checks_expiring']++;
            }
            if ($row['checks_flagged'] ?? false) {
                $counts['checks_flagged']++;
            }
            if ($row['compliance_missing'] ?? false) {
                $counts['compliance_missing']++;
            }
        }

        return $counts;
    }
}
