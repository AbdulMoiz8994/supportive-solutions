<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToOrganization;

class Document extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'name',
        'path',
        'disk',
        'mime_type',
        'file_size',
        'original_filename',
        'type',
        'category',
        'expires_at',
        'verification_status',
        'is_signed',
        'uploaded_by',
        'signed_at',
        'organization_id',
    ];

    protected $casts = [
        'is_signed' => 'boolean',
        'signed_at' => 'datetime',
        'expires_at' => 'date',
    ];

    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExpiringSoon()
    {
        return $this->expires_at && 
               $this->expires_at->isFuture() && 
               $this->expires_at->diffInDays(now()) <= 30;
    }

    public function documentable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
