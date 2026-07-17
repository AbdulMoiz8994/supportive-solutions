<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaregiverAuditLog extends Model
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
