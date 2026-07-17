<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayRecord;
use Carbon\Carbon;

class PayrollEligibilityService
{
    public function resolveEligibleFrom(Employee $employee, ?Carbon $caseStart = null): Carbon
    {
        if ($employee->pay_eligibility_start) {
            return $employee->pay_eligibility_start->copy()->startOfDay();
        }

        $caseStart = ($caseStart ?? $this->resolveCaseStart($employee))->copy()->startOfDay();
        $champsDate = $employee->champs_association_date?->copy()->startOfDay() ?? $caseStart;

        return $caseStart->greaterThan($champsDate) ? $caseStart : $champsDate;
    }

    public function resolveCaseStart(Employee $employee): Carbon
    {
        $assignment = $employee->assignments()->where('status', 'Active')->orderBy('assigned_since')->first();

        if ($assignment?->assigned_since) {
            return $assignment->assigned_since->copy()->startOfDay();
        }

        return ($employee->activated_at ?? $employee->hire_date ?? now())->copy()->startOfDay();
    }

    public function champsAssociationDate(Employee $employee): ?Carbon
    {
        return $employee->champs_association_date?->copy()->startOfDay();
    }

    public function canBackdateToPeriod(PayRecord $record, Carbon $periodStart): bool
    {
        $type = $this->resolveCaregiverType($record);

        if ($type === PayRecord::CAREGIVER_AGENCY) {
            return false;
        }

        $eligibleFrom = $this->resolveEligibleFrom($record->employee);

        return $periodStart->greaterThanOrEqualTo($eligibleFrom);
    }

    public function assertBackdatingAllowed(PayRecord $record): void
    {
        $periodKey = $record->period_key ?? app(PayrollHoursResolver::class)->periodKeyFromLabel($record->period);

        if (! $periodKey) {
            return;
        }

        $periodStart = Carbon::createFromFormat('Y-m', $periodKey)->startOfMonth();

        if (! $this->canBackdateToPeriod($record, $periodStart)) {
            throw new \InvalidArgumentException('Backdating is not allowed for agency-sourced caregivers or before eligibility date.');
        }
    }

    public function resolveCaregiverType(PayRecord $record): string
    {
        if ($record->caregiver_type) {
            return $record->caregiver_type;
        }

        $employeeType = strtolower((string) ($record->employee?->caregiver_type ?? ''));

        if (str_contains($employeeType, 'family')) {
            return PayRecord::CAREGIVER_FAMILY;
        }

        if (str_contains($employeeType, 'agency')) {
            return PayRecord::CAREGIVER_AGENCY;
        }

        if ($record->employee?->relationship_to_client) {
            return PayRecord::CAREGIVER_FAMILY;
        }

        return PayRecord::CAREGIVER_AGENCY;
    }
}
