<?php

namespace App\Traits;

use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;

trait LocationAware
{
    /**
     * Boot the trait.
     */
    protected static function bootLocationAware()
    {
        if (session()->has('selected_location_id')) {
            static::addGlobalScope('location', function (Builder $builder) {
                $builder->where('location_id', session('selected_location_id'));
            });
        }
    }

    /**
     * Get the location for the model.
     */
    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
