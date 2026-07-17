<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationConnectionHealth extends Model
{
    public const STATUS_CONNECTED = 'connected';

    public const STATUS_ERROR = 'error';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_PENDING = 'pending';

    protected $table = 'integration_connection_health';

    protected $fillable = [
        'contact_id',
        'status',
        'message',
        'last_sync_at',
        'last_batch_at',
        'errors_30d',
        'last_tested_at',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'last_batch_at' => 'datetime',
        'last_tested_at' => 'datetime',
        'errors_30d' => 'integer',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CONNECTED => 'Connected',
            self::STATUS_ERROR => 'Error',
            self::STATUS_DISCONNECTED => 'Disconnected',
            self::STATUS_PENDING => 'Pending',
            default => 'Not configured',
        };
    }

    public function statusBadgeVariant(): string
    {
        return match ($this->status) {
            self::STATUS_CONNECTED => 'green',
            self::STATUS_ERROR => 'red',
            self::STATUS_PENDING => 'amber',
            self::STATUS_DISCONNECTED => 'gray',
            default => 'gray',
        };
    }
}
