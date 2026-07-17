<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRun extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'report_slug',
        'custom_report_id',
        'report_schedule_id',
        'user_id',
        'period',
        'format',
        'status',
        'row_count',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReportSchedule::class, 'report_schedule_id');
    }

    public function customReport(): BelongsTo
    {
        return $this->belongsTo(CustomReportDefinition::class, 'custom_report_id');
    }
}
