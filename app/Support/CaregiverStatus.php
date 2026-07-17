<?php

namespace App\Support;

use App\Models\Employee;

class CaregiverStatus
{
    public static function normalize(Employee $caregiver): string
    {
        if ($caregiver->status === 'On Hold') {
            return 'On Hold';
        }

        if ($caregiver->onboarding_status === 'Pending onboarding') {
            return 'Pending';
        }

        if ($caregiver->status === 'On Leave') {
            return 'On Leave';
        }

        $status = strtolower(trim((string) ($caregiver->status ?? '')));

        if (in_array($status, ['terminated', 'inactive', 'discharged'], true)) {
            return 'Inactive';
        }

        return 'Active';
    }

    public static function isActive(Employee $caregiver): bool
    {
        return static::normalize($caregiver) === 'Active';
    }
}
