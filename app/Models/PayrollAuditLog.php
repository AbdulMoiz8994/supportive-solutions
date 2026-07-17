<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class PayrollAuditLog extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function payRecord()
    {
        return $this->belongsTo(PayRecord::class);
    }
}
