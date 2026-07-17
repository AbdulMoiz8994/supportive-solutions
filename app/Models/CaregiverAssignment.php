<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaregiverAssignment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'live_in'        => 'boolean',
        'assigned_since' => 'date',
        'ended_at'       => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
