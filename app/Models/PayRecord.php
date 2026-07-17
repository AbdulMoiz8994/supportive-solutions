<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PayRecord extends Model
{
    use BelongsToOrganization;

    public const STATUS_AWAITING_FORM = 'Awaiting form';
    public const STATUS_PENDING = 'Pending';
    public const STATUS_READY = 'Ready';
    public const STATUS_IN_GRACE = 'In grace';
    public const STATUS_LATE_ROLLED = 'Late - rolled';
    public const STATUS_HELD = 'Held - review';
    public const STATUS_PAID = 'Paid';

    public const CAREGIVER_FAMILY = 'family';
    public const CAREGIVER_AGENCY = 'agency';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'client_id',
        'compliance_form_id',
        'period',
        'period_key',
        'hours_source',
        'caregiver_type',
        'program_tag',
        'hold_reason',
        'exported_at',
    ];

    protected $guarded = [
        'id',
        'status',
        'hours',
        'rate',
        'gross',
        'batch_id',
        'paid_date',
        'stub_path',
        'grace_end_date',
        'verified_at',
        'lifecycle_events',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'paid_date'        => 'date',
        'grace_end_date'   => 'date',
        'verified_at'      => 'datetime',
        'locked_at'        => 'datetime',
        'exported_at'      => 'datetime',
        'hours'            => 'decimal:2',
        'rate'             => 'decimal:2',
        'gross'            => 'decimal:2',
        'lifecycle_events' => 'array',
    ];

    public static function statuses(): array
    {
        return array_values(config('payroll.statuses', []));
    }

    public static function tabStatuses(): array
    {
        return [
            'ready'       => self::STATUS_READY,
            'in_grace'    => self::STATUS_IN_GRACE,
            'late_rolled' => self::STATUS_LATE_ROLLED,
            'held'        => self::STATUS_HELD,
            'paid'        => self::STATUS_PAID,
        ];
    }

    public static function mapLegacyStatus(?string $status): ?string
    {
        return match ($status) {
            'Pending'        => self::STATUS_PENDING,
            'Awaiting form'  => self::STATUS_AWAITING_FORM,
            'Paid'           => self::STATUS_PAID,
            default          => $status,
        };
    }

    public function scopeForPeriod(Builder $query, string $periodKey): Builder
    {
        return $query->where('period_key', $periodKey);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READY);
    }

    public function scopeInGrace(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN_GRACE);
    }

    public function scopeHeld(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_HELD);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isImmutable(): bool
    {
        return $this->isPaid() || $this->locked_at !== null;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function complianceForm()
    {
        return $this->belongsTo(ComplianceForm::class);
    }

    public function batch()
    {
        return $this->belongsTo(PayrollBatch::class, 'batch_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(PayrollAuditLog::class);
    }

    public function payrollClaims()
    {
        return $this->hasMany(PayrollClaim::class);
    }

    public function latestPayrollClaim()
    {
        return $this->hasOne(PayrollClaim::class)->latestOfMany();
    }

    public function lockedByUser()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
