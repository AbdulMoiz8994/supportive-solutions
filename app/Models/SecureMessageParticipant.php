<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecureMessageParticipant extends Model
{
    protected $fillable = [
        'thread_id',
        'user_id',
        'last_read_at',
        'muted_at',
        'archived_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'muted_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(SecureMessageThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
