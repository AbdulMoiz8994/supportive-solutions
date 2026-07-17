<?php

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\User;

class ApiKeyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, ApiKey $apiKey): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, ApiKey $apiKey): bool
    {
        return $user->isSuperAdmin();
    }

    public function toggle(User $user, ApiKey $apiKey): bool
    {
        return $user->isSuperAdmin();
    }

    public function regenerate(User $user, ApiKey $apiKey): bool
    {
        return $user->isSuperAdmin();
    }
}
