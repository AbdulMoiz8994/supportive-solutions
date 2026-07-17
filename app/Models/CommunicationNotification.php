<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommunicationNotification extends Model
{
    use BelongsToOrganization;

    public const TYPE_COMMUNICATION_SENT = 'communication_sent';

    public const TYPE_COMMUNICATION_FAILED = 'communication_failed';

    public const TYPE_SECURE_MESSAGE = 'secure_message';

    public const TYPE_COMMUNICATION_RECEIVED = 'communication_received';

    protected $fillable = [
        'organization_id',
        'user_id',
        'type',
        'title',
        'body',
        'related_type',
        'related_id',
        'read_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
