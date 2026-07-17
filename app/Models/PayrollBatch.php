<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class PayrollBatch extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    protected $casts = [
        'build_date'             => 'date',
        'pay_date'               => 'date',
        'built_at'               => 'datetime',
        'approved_at'            => 'datetime',
        'accountant_notified_at' => 'datetime',
        'aw_synced_at'           => 'datetime',
        'aw_payroll_meta'        => 'array',
        'total_gross'            => 'decimal:2',
    ];

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function payRecords()
    {
        return $this->hasMany(PayRecord::class, 'batch_id');
    }

    public function builder()
    {
        return $this->belongsTo(User::class, 'built_by');
    }
}
