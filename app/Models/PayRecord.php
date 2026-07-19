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
    public const STATUS_REVERSAL = 'Reversal';

    public const CAREGIVER_FAMILY = 'family';
    public const CAREGIVER_AGENCY = 'agency';

    // P5 — corrections / reversals / supplemental pay
    public const RECORD_REGULAR = 'regular';
    public const RECORD_SUPPLEMENTAL = 'supplemental';
    public const RECORD_REVERSAL = 'reversal';

    public const RECOVERY_REQUESTED = 'Requested';
    public const RECOVERY_RECOVERED = 'Recovered';

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
        'record_type',
        'adjustment_reason',
        'service_dates',
        'recovery_status',
        'parent_pay_record_id',
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
        'processed_payroll_at' => 'datetime',
        'hours'            => 'decimal:2',
        'rate'             => 'decimal:2',
        'gross'            => 'decimal:2',
        'net'              => 'decimal:2',
        'recovery_amount'  => 'decimal:2',
        'lifecycle_events' => 'array',
    ];

    public function isRegular(): bool
    {
        return ($this->record_type ?? self::RECORD_REGULAR) === self::RECORD_REGULAR;
    }

    public function isSupplemental(): bool
    {
        return $this->record_type === self::RECORD_SUPPLEMENTAL;
    }

    public function isReversal(): bool
    {
        return $this->record_type === self::RECORD_REVERSAL;
    }

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

    public function processedByUser()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /** The original run this supplemental/reversal adjusts (P5). */
    public function parentPayRecord()
    {
        return $this->belongsTo(PayRecord::class, 'parent_pay_record_id');
    }

    /** Supplemental/reversal records attached to this original run (P5). */
    public function adjustments()
    {
        return $this->hasMany(PayRecord::class, 'parent_pay_record_id');
    }
}
