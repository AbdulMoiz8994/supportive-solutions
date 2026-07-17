<?php

namespace App\Helpers;

use App\Models\User;

class SettingsHelper
{
    public static function canAccessHome(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user && ($user->isSuperAdmin() || $user->role === User::ROLE_ADMIN);
    }

    public static function homeUrl(): string
    {
        return static::canAccessHome() ? route('settings.index') : route('profile');
    }
}
