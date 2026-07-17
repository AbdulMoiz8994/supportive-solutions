<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FormSubmission extends Model
{
    use BelongsToOrganization;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_AWAITING_SIGNATURE = 'awaiting_signature';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_VOIDED = 'voided';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'organization_id',
        'form_template_id',
        'subject_type',
        'subject_id',
        'status',
        'field_values',
        'fields_snapshot',
        'signed_at',
        'signed_by_name',
        'document_id',
        'created_by_user_id',
        'created_by_agent_id',
        'locked_at',
        'expires_at',
        'voided_at',
        'void_reason',
        'signing_token',
        'signature_image',
        'esign_sent_at',
        'esign_channel',
        'esign_external_id',
    ];

    protected $casts = [
        'field_values' => 'array',
        'fields_snapshot' => 'array',
        'signed_at' => 'datetime',
        'locked_at' => 'datetime',
        'expires_at' => 'datetime',
        'voided_at' => 'datetime',
        'esign_sent_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function createdByAgent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'created_by_agent_id');
    }

    public function isLocked(): bool
    {
        // Awaiting remote signature stays editable by staff until signed/voided/expired.
        if ($this->status === self::STATUS_AWAITING_SIGNATURE) {
            return false;
        }

        return in_array($this->status, [
            self::STATUS_SIGNED,
            self::STATUS_VOIDED,
            self::STATUS_EXPIRED,
        ], true) || $this->locked_at !== null;
    }

    public function subjectName(): string
    {
        $subject = $this->subject;

        if ($subject instanceof Client || $subject instanceof Employee) {
            return trim($subject->first_name.' '.$subject->last_name);
        }

        return 'Unknown';
    }
}
