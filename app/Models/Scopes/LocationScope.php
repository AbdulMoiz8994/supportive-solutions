<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class LocationScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $locationId = session('selected_location_id');
        
        // If no location is in session, we might want to default to nothing or everything.
        // For "Company Wide" or similar logic, we check if locationId is set.
        if ($locationId) {
            $builder->where($model->getTable() . '.location_id', $locationId);
        }
    }
}
