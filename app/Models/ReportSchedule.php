<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportSchedule extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'report_slug',
        'custom_report_id',
        'frequency',
        'format',
        'recipients',
        'filters',
        'is_active',
        'next_run_at',
        'last_run_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'filters' => 'array',
        'is_active' => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customReport(): BelongsTo
    {
        return $this->belongsTo(CustomReportDefinition::class, 'custom_report_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ReportRun::class);
    }
}
