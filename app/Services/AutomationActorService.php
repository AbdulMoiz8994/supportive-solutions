<?php

namespace App\Services;

use App\Models\User;

/**
 * Resolves a stable "actor" user for scheduled automations (billing submit,
 * background jobs) when no human is at the keyboard.
 */
class AutomationActorService
{
    public function actorForOrganization(?int $organizationId): ?User
    {
        return User::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }
}
