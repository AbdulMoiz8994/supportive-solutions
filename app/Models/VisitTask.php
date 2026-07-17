<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single care-task checklist item on a scheduled visit.
 */
class VisitTask extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'schedule_id',
        'client_id',
        'label',
        'category',
        'sort_order',
        'is_completed',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
