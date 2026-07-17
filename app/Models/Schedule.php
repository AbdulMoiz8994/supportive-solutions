<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory, BelongsToOrganization, LogsActivity, SoftDeletes;

    public const STATUS_SCHEDULED = 'Scheduled';

    public const STATUS_CLOCKED_IN = 'Clocked In';

    public const STATUS_COMPLETED = 'Completed';

    public const STATUS_MISSED = 'Missed';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUS_NO_SHOW = 'No Show';

    /**
     * Legacy value kept for backward compatibility with existing records.
     */
    public const STATUS_IN_PROGRESS = 'In-Progress';

    public const EVENT_INTAKE = 'intake';

    public const EVENT_REASSESSMENT = 'reassessment';

    public const EVENT_FOLLOW_UP = 'follow_up';

    public const EVENT_CARE_VISIT = 'care_visit';

    public const EVENT_INTERNAL = 'internal';

    public const EVENT_OTHER = 'other';

    public static function inProgressStatuses(): array
    {
        return [self::STATUS_CLOCKED_IN, self::STATUS_IN_PROGRESS];
    }

    /**
     * @return list<string>
     */
    public static function eventTypes(): array
    {
        return [
            self::EVENT_INTAKE,
            self::EVENT_REASSESSMENT,
            self::EVENT_FOLLOW_UP,
            self::EVENT_CARE_VISIT,
            self::EVENT_INTERNAL,
            self::EVENT_OTHER,
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_SCHEDULED,
            self::STATUS_CLOCKED_IN,
            self::STATUS_COMPLETED,
            self::STATUS_MISSED,
            self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW,
            self::STATUS_IN_PROGRESS,
            'scheduled',
            'completed',
            'cancelled',
            'no_show',
        ];
    }

    public static function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'scheduled' => self::STATUS_SCHEDULED,
            'completed' => self::STATUS_COMPLETED,
            'cancelled', 'canceled' => self::STATUS_CANCELLED,
            'no_show', 'no show' => self::STATUS_NO_SHOW,
            'clocked_in', 'clocked in' => self::STATUS_CLOCKED_IN,
            'in_progress', 'in-progress' => self::STATUS_IN_PROGRESS,
            'missed' => self::STATUS_MISSED,
            default => $status,
        };
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\LocationScope);

        static::saving(function (Schedule $schedule) {
            $schedule->syncDateTimeFields();
        });
    }

    protected $fillable = [
        'client_id',
        'employee_id',
        'title',
        'description',
        'event_type',
        'created_by',
        'date',
        'start_time',
        'end_time',
        'start_at',
        'end_at',
        'timezone',
        'address',
        'all_day',
        'metadata',
        'actual_clock_in',
        'actual_clock_out',
        'total_hours',
        'status',
        'evv_status',
        'visit_notes',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_out_latitude',
        'clock_out_longitude',
        'billing_id',
        'location_id',
    ];

    protected $casts = [
        'visit_notes' => 'json',
        'metadata' => 'json',
        'evv_status' => 'boolean',
        'all_day' => 'boolean',
        'date' => 'date',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'actual_clock_in' => 'datetime',
        'actual_clock_out' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function visitTasks(): HasMany
    {
        return $this->hasMany(VisitTask::class)->orderBy('sort_order');
    }

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, self::inProgressStatuses(), true);
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_CANCELLED, 'cancelled'], true);
    }

    public function getScheduledHoursAttribute(): float
    {
        if ($this->start_at && $this->end_at) {
            return round(max(0, $this->start_at->diffInMinutes($this->end_at)) / 60, 2);
        }

        if (! $this->start_time || ! $this->end_time) {
            return 0;
        }

        try {
            $start = Carbon::parse($this->start_time);
            $end = Carbon::parse($this->end_time);

            if ($end->lessThan($start)) {
                $end->addDay();
            }

            return round($start->diffInMinutes($end) / 60, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getEventTypeLabelAttribute(): string
    {
        return str_replace('_', ' ', ucfirst((string) $this->event_type));
    }

    public function syncDateTimeFields(): void
    {
        if ($this->start_at && ! $this->date) {
            $this->date = $this->start_at->toDateString();
            $this->start_time = $this->start_at->format('H:i:s');
        }

        if ($this->end_at && ! $this->end_time) {
            $this->end_time = $this->end_at->format('H:i:s');
        }

        if ($this->date && $this->start_time) {
            $this->start_at = Carbon::parse($this->date->format('Y-m-d').' '.$this->formatTimeValue($this->start_time));
        }

        if ($this->date && $this->end_time) {
            $end = Carbon::parse($this->date->format('Y-m-d').' '.$this->formatTimeValue($this->end_time));

            if ($this->start_at && $end->lessThanOrEqualTo($this->start_at)) {
                $end->addDay();
            }

            $this->end_at = $end;
        }

        if (! $this->timezone) {
            $this->timezone = config('app.timezone', 'UTC');
        }
    }

    public function scopeScheduleSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '' || strlen($search) > 100) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

        return $query->where(function (Builder $builder) use ($like) {
            $builder->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('client', function (Builder $clientQuery) use ($like) {
                    $clientQuery->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);
                })
                ->orWhereHas('employee', function (Builder $employeeQuery) use ($like) {
                    $employeeQuery->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);
                });
        });
    }

    public function scopeFilterEventType(Builder $query, ?string $eventType): Builder
    {
        if (! filled($eventType)) {
            return $query;
        }

        return $query->where('event_type', $eventType);
    }

    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        if (! filled($status)) {
            return $query;
        }

        return $query->where('status', Schedule::normalizeStatus($status));
    }

    public function scopeFilterDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if (filled($from)) {
            $query->whereRaw('DATE(COALESCE(start_at, date)) >= ?', [$from]);
        }

        if (filled($to)) {
            $query->whereRaw('DATE(COALESCE(start_at, date)) <= ?', [$to]);
        }

        return $query;
    }

    public function scopeOverlapping(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }

    /**
     * Returns which side conflicts ('caregiver' or 'client'), or null if the slot is free.
     */
    public static function conflictFor(?int $employeeId, ?int $clientId, Carbon $start, Carbon $end, ?int $excludeId = null): ?string
    {
        if ($employeeId) {
            $exists = self::withoutGlobalScopes()
                ->where('employee_id', $employeeId)
                ->whereNotIn('status', [self::STATUS_CANCELLED, 'cancelled'])
                ->when($excludeId, fn (Builder $q) => $q->whereKeyNot($excludeId))
                ->overlapping($start, $end)
                ->exists();

            if ($exists) {
                return 'caregiver';
            }
        }

        if ($clientId) {
            $exists = self::withoutGlobalScopes()
                ->where('client_id', $clientId)
                ->whereNotIn('status', [self::STATUS_CANCELLED, 'cancelled'])
                ->when($excludeId, fn (Builder $q) => $q->whereKeyNot($excludeId))
                ->overlapping($start, $end)
                ->exists();

            if ($exists) {
                return 'client';
            }
        }

        return null;
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->where('start_at', '>=', now())
                ->orWhere(function (Builder $fallback) {
                    $fallback->whereNull('start_at')
                        ->whereDate('date', '>=', today());
                });
        });
    }

    public function scopeScheduleSort(Builder $query, ?string $sort, ?string $direction = 'asc'): Builder
    {
        $direction = strtolower((string) $direction) === 'desc' ? 'desc' : 'asc';
        $allowed = ['start_at', 'date', 'title', 'status', 'event_type', 'created_at'];

        if (! in_array($sort, $allowed, true)) {
            $sort = 'start_at';
        }

        if ($sort === 'start_at') {
            return $query->orderByRaw('COALESCE(start_at, date) '.$direction)->orderBy('id', $direction);
        }

        return $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }

    private function formatTimeValue(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('H:i:s');
        }

        $stringValue = (string) $value;

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $stringValue)) {
            return strlen($stringValue) === 5 ? $stringValue.':00' : $stringValue;
        }

        return Carbon::parse($stringValue)->format('H:i:s');
    }
}
