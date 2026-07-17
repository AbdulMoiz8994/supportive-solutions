<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackgroundCheck extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_run'    => 'date',
        'next_due'    => 'date',
        'approved_at' => 'date',
        'is_exempt'   => 'boolean',
        'is_custom'   => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
