<?php

namespace App\Services;

use App\Models\PayRecord;

class PayrollCalculationService
{
    public function calculateGross(?float $hours, ?float $rate): float
    {
        $hours = max(0, (float) ($hours ?? 0));
        $rate = max(0, (float) ($rate ?? 0));

        return round($hours * $rate, 2);
    }

    public function resolveRate(?float $employeeWage): float
    {
        $wage = $employeeWage ?? config('payroll.wage.default_hourly', 15.00);

        return round((float) $wage, 2);
    }

    public function applyCalculation(PayRecord $record, ?float $hours = null, ?float $rate = null): PayRecord
    {
        /** @var \App\Models\PayRecord $record */
        $hours = $hours ?? $record->hours;
        $rate = $rate ?? $record->rate ?? $this->resolveRate($record->employee?->hourly_wage);
        $record->rate = $rate;
        $record->hours = $hours;
        $record->gross = $this->calculateGross($hours, $rate);

        return $record;
    }
}
