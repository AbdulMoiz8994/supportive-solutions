<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SecureMessageThread extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'subject',
        'related_type',
        'related_id',
        'created_by',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SecureMessage::class, 'thread_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SecureMessage::class, 'thread_id')->latestOfMany();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(SecureMessageParticipant::class, 'thread_id');
    }
}
