<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollClaim extends Model
{
    use BelongsToOrganization;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'pay_record_id',
        'employee_id',
        'claim_reference_id',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'submitted_at',
    ];

    protected $casts = [
        'request_payload'  => 'array',
        'response_payload' => 'array',
        'submitted_at'   => 'datetime',
    ];

    public function payRecord(): BelongsTo
    {
        return $this->belongsTo(PayRecord::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public static function terminalStatuses(): array
    {
        return [
            self::STATUS_SUBMITTED,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_FAILED,
        ];
    }

    public function isRetryable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_FAILED, self::STATUS_PENDING], true);
    }
}
