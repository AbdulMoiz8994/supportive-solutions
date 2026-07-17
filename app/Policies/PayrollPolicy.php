<?php

namespace App\Policies;

use App\Models\PayRecord;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;

class PayrollPolicy
{
    use InteractsWithOrganization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'view_payroll');
    }

    public function view(User $user, PayRecord $record): bool
    {
        return $this->hasPermission($user, 'view_payroll')
            && $this->sameOrganization($user, $record);
    }

    public function updateWage(User $user, PayRecord $record): bool
    {
        return $this->hasPermission($user, 'edit_payroll')
            && $this->sameOrganization($user, $record)
            && ! $record->isImmutable();
    }

    public function buildBatch(User $user): bool
    {
        return $this->hasPermission($user, 'run_payroll')
            && ($user->isSuperAdmin() || $user->organization_id);
    }

    public function releaseHold(User $user, PayRecord $record): bool
    {
        return $this->hasPermission($user, 'release_payroll_hold')
            && $this->sameOrganization($user, $record)
            && ! $record->isImmutable();
    }

    public function export(User $user): bool
    {
        return $this->hasPermission($user, 'export_payroll');
    }

    public function downloadStub(User $user, PayRecord $record): bool
    {
        return $this->hasPermission($user, 'view_payroll')
            && $this->sameOrganization($user, $record);
    }

    public function applyHold(User $user, PayRecord $record): bool
    {
        return $this->hasPermission($user, 'edit_payroll')
            && $this->sameOrganization($user, $record)
            && ! $record->isImmutable();
    }

    public function approveBatch(User $user): bool
    {
        return $this->hasPermission($user, 'run_payroll');
    }
}
