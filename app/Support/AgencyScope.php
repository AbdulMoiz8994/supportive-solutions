<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AgencyScope
{
    public static function organizationId(?User $user = null): ?int
    {
        $user ??= auth()->user();

        if (! $user || $user->isSuperAdmin()) {
            return null;
        }

        return $user->organization_id;
    }

    public static function applyOrganization(Builder $query, ?int $orgId = null, string $column = 'organization_id'): Builder
    {
        $orgId ??= static::organizationId();

        if ($orgId) {
            $query->where($column, $orgId);
        }

        return $query;
    }
}
