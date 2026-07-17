<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaregiverCommunication extends Model
{
    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
