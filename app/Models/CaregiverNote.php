<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaregiverNote extends Model
{
    protected $guarded = [];

    protected $casts = [
        'pinned'   => 'boolean',
        'noted_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
