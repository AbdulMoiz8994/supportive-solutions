<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalIntegrationHealth extends Model
{
    public const STATUS_CONNECTED = 'connected';

    public const STATUS_ERROR = 'error';

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_PARTIAL = 'partial';

    protected $table = 'global_integration_health';

    protected $fillable = [
        'slug',
        'status',
        'message',
        'latency_ms',
        'details',
        'last_tested_at',
        'last_tested_by',
    ];

    protected $casts = [
        'last_tested_at' => 'datetime',
        'details' => 'array',
        'latency_ms' => 'integer',
    ];

    public function lastTestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_tested_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CONNECTED => 'Connected',
            self::STATUS_PARTIAL => 'Partial',
            self::STATUS_ERROR => 'Error',
            default => 'Not configured',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_CONNECTED => 'bg-emerald-50 text-emerald-700 border border-emerald-100',
            self::STATUS_PARTIAL => 'bg-amber-50 text-amber-700 border border-amber-100',
            self::STATUS_ERROR => 'bg-red-50 text-red-700 border border-red-100',
            default => 'bg-slate-100 text-slate-600 border border-slate-200',
        };
    }

    /**
     * @return list<array{name: string, passed: bool, detail: string, duration_ms?: ?int}>
     */
    public function checks(): array
    {
        return $this->details['checks'] ?? [];
    }

    public function recommendation(): ?string
    {
        return $this->details['recommendation'] ?? null;
    }

    public function detailMessage(): ?string
    {
        return $this->details['message'] ?? $this->message;
    }

    public function displayStatus(): string
    {
        $when = $this->last_tested_at?->format('M j g:ia');
        $latency = $this->latency_ms ? $this->latency_ms.'ms' : null;
        $headline = $this->detailMessage() ?? $this->message ?? $this->statusLabel();

        if (! $when) {
            return $headline;
        }

        $parts = array_filter([$this->statusLabel(), $when, $latency, $headline]);

        return implode(' · ', $parts);
    }
}
