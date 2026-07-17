<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowQueueItem extends Model
{
    public const TYPE_APPROVAL = 'approval';

    public const TYPE_HUMAN_TASK = 'human_task';

    public const TYPE_EXCEPTION = 'exception';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_HELD = 'held';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'organization_id',
        'queue_type',
        'slug',
        'status',
        'meta',
        'subject_type',
        'subject_id',
        'sla_due_at',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
