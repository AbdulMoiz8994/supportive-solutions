<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationAttachment extends Model
{
    protected $fillable = [
        'communication_id',
        'organization_id',
        'original_name',
        'stored_path',
        'disk',
        'mime_type',
        'file_size',
    ];

    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }
}
