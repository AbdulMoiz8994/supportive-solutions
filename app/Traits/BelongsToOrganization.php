<?php

namespace App\Traits;

use App\Models\Organization;
use App\Support\CommunicationOrganizationResolver;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization()
    {
        static::creating(function ($model) {
            if (! empty($model->organization_id)) {
                return;
            }

            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            if ($user->organization_id) {
                $model->organization_id = $user->organization_id;

                return;
            }

            $model->organization_id = CommunicationOrganizationResolver::resolve($user);
        });

        static::addGlobalScope('organization', function (Builder $builder) {
            if (auth()->check() && !auth()->user()->isSuperAdmin()) {
                $builder->where('organization_id', auth()->user()->organization_id);
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
