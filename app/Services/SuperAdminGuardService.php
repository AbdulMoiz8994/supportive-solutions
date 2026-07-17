<?php

namespace App\Services;

use App\Models\User;

class SuperAdminGuardService
{
    public function superAdminCount(): int
    {
        return User::where('role', User::ROLE_SUPER_ADMIN)->count();
    }

    public function isLastSuperAdmin(User $user): bool
    {
        return $user->isSuperAdmin() && $this->superAdminCount() <= 1;
    }

    public function canDemoteSuperAdmin(User $target, string $newRole): bool
    {
        if (! $target->isSuperAdmin() || $newRole === User::ROLE_SUPER_ADMIN) {
            return true;
        }

        return $this->superAdminCount() > 1;
    }

    public function canDeleteUser(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        if ($this->isLastSuperAdmin($target)) {
            return false;
        }

        return true;
    }
}
