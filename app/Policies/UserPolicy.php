<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;
use App\Services\SuperAdminGuardService;

class UserPolicy
{
    use InteractsWithOrganization;

    public function __construct(
        protected SuperAdminGuardService $superAdminGuard
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'view_staff');
    }

    public function view(User $user, User $staffUser): bool
    {
        return $this->hasPermission($user, 'view_staff') && $this->canManageStaffTarget($user, $staffUser);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'add_staff');
    }

    public function update(User $user, User $staffUser): bool
    {
        return $this->hasPermission($user, 'edit_staff') && $this->canManageStaffTarget($user, $staffUser);
    }

    public function toggleStatus(User $user, User $staffUser): bool
    {
        return $this->update($user, $staffUser);
    }

    public function managePermissions(User $user): bool
    {
        return $this->hasPermission($user, 'manage_permissions');
    }

    public function resetPassword(User $user, User $staffUser): bool
    {
        return $this->update($user, $staffUser);
    }

    public function revokeSessions(User $user, User $staffUser): bool
    {
        return $this->update($user, $staffUser);
    }

    public function updateProfile(User $user, User $profileUser): bool
    {
        return (int) $user->id === (int) $profileUser->id;
    }

    public function managePlatformUsers(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function createPlatformUser(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function updatePlatformUser(User $user, User $managedUser): bool
    {
        return $user->isSuperAdmin();
    }

    public function deletePlatformUser(User $user, User $managedUser): bool
    {
        if (! $user->isSuperAdmin()) {
            return false;
        }

        return $this->superAdminGuard->canDeleteUser($user, $managedUser);
    }
}
