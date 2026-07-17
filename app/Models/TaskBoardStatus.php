<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class TaskBoardStatus extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'key',
        'label',
        'sort_order',
        'header_bg',
        'badge_bg',
        'badge_text',
        'is_closed',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
        'sort_order' => 'integer',
    ];
}
