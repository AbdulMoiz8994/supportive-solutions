<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Location $location): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Location $location): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Location $location): bool
    {
        return $user->isSuperAdmin();
    }
}
