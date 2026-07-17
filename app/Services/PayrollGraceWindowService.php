<?php

namespace App\Services;

use App\Models\PayRecord;
use Carbon\Carbon;

class PayrollGraceWindowService
{
    public function graceDays(): int
    {
        return (int) config('payroll.grace_days', 10);
    }

    public function graceEndDate(?Carbon $submittedAt): ?Carbon
    {
        if (! $submittedAt) {
            return null;
        }

        return $submittedAt->copy()->addDays($this->graceDays())->startOfDay();
    }

    public function isInGrace(?Carbon $submittedAt, ?Carbon $asOf = null): bool
    {
        $graceEnd = $this->graceEndDate($submittedAt);

        if (! $graceEnd) {
            return false;
        }

        $asOf = ($asOf ?? now())->copy()->startOfDay();

        return $asOf->lt($graceEnd);
    }

    public function daysRemaining(?Carbon $submittedAt, ?Carbon $asOf = null): ?int
    {
        $graceEnd = $this->graceEndDate($submittedAt);

        if (! $graceEnd) {
            return null;
        }

        $asOf = ($asOf ?? now())->copy()->startOfDay();

        if (! $asOf->lt($graceEnd)) {
            return 0;
        }

        return (int) $asOf->diffInDays($graceEnd);
    }

    public function graceStatus(?Carbon $submittedAt, ?Carbon $asOf = null): ?string
    {
        if (! $submittedAt) {
            return null;
        }

        if ($this->isInGrace($submittedAt, $asOf)) {
            return PayRecord::STATUS_IN_GRACE;
        }

        return PayRecord::STATUS_READY;
    }
}
