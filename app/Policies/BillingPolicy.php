<?php

namespace App\Policies;

use App\Models\Billing;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;

class BillingPolicy
{
    use InteractsWithOrganization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'view_billing');
    }

    public function view(User $user, Billing $billing): bool
    {
        return $this->hasPermission($user, 'view_billing') && $this->sameOrganization($user, $billing);
    }

    public function runCycle(User $user): bool
    {
        return $this->hasPermission($user, 'run_billing') && ($user->isSuperAdmin() || $user->organization_id);
    }
}
