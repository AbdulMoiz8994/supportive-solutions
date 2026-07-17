<?php

namespace App\Policies;

use App\Models\BillingClaimAudit;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;

class BillingClaimAuditPolicy
{
    use InteractsWithOrganization;

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'view_billing_claims_audit');
    }

    public function view(User $user, BillingClaimAudit $audit): bool
    {
        return $this->hasPermission($user, 'view_billing_claims_audit')
            && $this->sameOrganization($user, $audit);
    }

    public function update(User $user, BillingClaimAudit $audit): bool
    {
        return $this->hasPermission($user, 'edit_billing_claims_audit')
            && $this->sameOrganization($user, $audit);
    }

    public function runActions(User $user): bool
    {
        return $this->hasPermission($user, 'edit_billing_claims_audit')
            && ($user->isSuperAdmin() || $user->organization_id);
    }

    public function override(User $user, BillingClaimAudit $audit): bool
    {
        return $this->hasPermission($user, 'override_billing_claims_audit')
            && $this->sameOrganization($user, $audit);
    }
}
