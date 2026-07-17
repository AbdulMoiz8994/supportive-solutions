<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait InteractsWithOrganization
{
    protected function sameOrganization(User $user, Model $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->organization_id || ! $model->getAttribute('organization_id')) {
            return false;
        }

        return (int) $user->organization_id === (int) $model->organization_id;
    }

    protected function hasPermission(User $user, string $permission): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission($permission);
    }

    protected function isOfficeTeam(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->role === User::ROLE_ADMIN
            || $user->role === User::ROLE_STAFF;
    }

    protected function canManageStaffTarget(User $actor, User $target): bool
    {
        if ($target->isSuperAdmin()) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($actor->organization_id && $target->organization_id) {
            return (int) $actor->organization_id === (int) $target->organization_id;
        }

        return true;
    }
}
