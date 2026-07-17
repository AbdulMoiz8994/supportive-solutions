<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecureMessage extends Model
{
    protected $fillable = [
        'thread_id',
        'sender_id',
        'body',
        'metadata',
        'read_at',
    ];

    protected $casts = [
        'body' => 'encrypted',
        'metadata' => 'array',
        'read_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(SecureMessageThread::class, 'thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
