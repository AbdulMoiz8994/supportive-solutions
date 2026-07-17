<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Communication extends Model
{
    use BelongsToOrganization, SoftDeletes;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_FAX = 'fax';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_CALL = 'call';

    public const CHANNEL_NOTE = 'note';

    public const CHANNEL_INTERNAL_MESSAGE = 'internal_message';

    public const CHANNEL_SYSTEM = 'system';

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const DIRECTION_INTERNAL = 'internal';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_READ = 'read';

    public const STATUS_ARCHIVED = 'archived';

    public const TRIAGE_CATEGORY_BILLING = 'billing';

    public const TRIAGE_CATEGORY_SCHEDULING = 'scheduling';

    public const TRIAGE_CATEGORY_WELLNESS = 'wellness';

    public const TRIAGE_CATEGORY_CLINICAL = 'clinical';

    public const TRIAGE_CATEGORY_CONCERN = 'concern';

    public const TRIAGE_CATEGORY_GENERAL = 'general';

    public const TRIAGE_PRIORITY_NORMAL = 'normal';

    public const TRIAGE_PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'organization_id',
        'related_type',
        'related_id',
        'template_id',
        'channel',
        'direction',
        'subject',
        'body',
        'status',
        'sender_id',
        'recipient_type',
        'recipient_id',
        'recipient_name',
        'recipient_email',
        'recipient_phone',
        'recipient_fax',
        'provider_message_id',
        'failure_reason',
        'metadata',
        'ai_triage_category',
        'ai_triage_priority',
        'concern_flagged',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'recipient_email' => 'encrypted',
        'recipient_phone' => 'encrypted',
        'recipient_fax' => 'encrypted',
        'metadata' => 'array',
        'concern_flagged' => 'boolean',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'template_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CommunicationAttachment::class);
    }

    public static function channels(): array
    {
        return [
            self::CHANNEL_EMAIL,
            self::CHANNEL_FAX,
            self::CHANNEL_SMS,
            self::CHANNEL_CALL,
            self::CHANNEL_NOTE,
            self::CHANNEL_INTERNAL_MESSAGE,
            self::CHANNEL_SYSTEM,
        ];
    }

    public static function directions(): array
    {
        return [
            self::DIRECTION_INBOUND,
            self::DIRECTION_OUTBOUND,
            self::DIRECTION_INTERNAL,
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_QUEUED,
            self::STATUS_SENT,
            self::STATUS_FAILED,
            self::STATUS_RECEIVED,
            self::STATUS_READ,
            self::STATUS_ARCHIVED,
        ];
    }
}
