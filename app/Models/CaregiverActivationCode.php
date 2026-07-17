<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverActivationCode extends Model
{
    use BelongsToOrganization;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVATED = 'activated';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'code',
        'status',
        'issued_at',
        'expires_at',
        'activated_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function caregiverName(): string
    {
        if (! $this->employee) {
            return '(unassigned)';
        }

        return trim($this->employee->first_name.' '.$this->employee->last_name);
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVATED => 'green',
            self::STATUS_PENDING => 'amber',
            self::STATUS_EXPIRED => 'gray',
            self::STATUS_REVOKED => 'red',
            default => 'gray',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVATED => 'Activated',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_REVOKED => 'Revoked',
            default => ucfirst($this->status),
        };
    }

    public static function generateCode(): string
    {
        $segments = [];

        for ($i = 0; $i < 3; $i++) {
            $segments[] = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4));
        }

        return 'SSHC-'.implode('-', $segments);
    }
}
