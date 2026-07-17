<?php

namespace App\Models;

use App\Services\TaskBoardStatusService;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Task extends Model
{
    use BelongsToOrganization;

    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_REOPEN = 'reopen';

    /** Virtual display-only status — never persisted. */
    public const STATUS_OVERDUE = 'overdue';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const ASSIGNEE_USER = 'user';

    public const ASSIGNEE_AGENT = 'agent';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SYSTEM = 'system';

    protected $fillable = [
        'organization_id',
        'location_id',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'assignee_type',
        'assignee_user_id',
        'assignee_agent_id',
        'client_id',
        'employee_id',
        'related_type',
        'related_id',
        'source',
        'created_by',
        'completed_at',
        'awaiting_approval',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'awaiting_approval' => 'boolean',
    ];

    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function assigneeAgent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'assignee_agent_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    public function effectiveStatus(): string
    {
        if (app(TaskBoardStatusService::class)->isClosedStatus($this->organization_id, $this->status)) {
            return $this->status;
        }

        if ($this->due_date && $this->due_date->isPast() && ! $this->due_date->isToday()) {
            return self::STATUS_OVERDUE;
        }

        return $this->status;
    }

    public function isOverdue(): bool
    {
        if (app(TaskBoardStatusService::class)->isClosedStatus($this->organization_id, $this->status)) {
            return false;
        }

        return $this->due_date
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }

    /**
     * Product decision: Overdue stays a display overlay (not a board column).
     * Overdue open tasks elevate to High priority for visibility and filters.
     */
    public function effectivePriority(): string
    {
        if ($this->isOverdue()) {
            return self::PRIORITY_HIGH;
        }

        return $this->priority ?: self::PRIORITY_MEDIUM;
    }

    /**
     * @return list<string>
     */
    public static function boardStatusKeys(): array
    {
        return collect(config('tasks.board_statuses', []))
            ->pluck('key')
            ->filter()
            ->values()
            ->all();
    }

    public static function boardStatusLabel(string $key): string
    {
        $match = collect(config('tasks.board_statuses', []))->firstWhere('key', $key);

        return $match['label'] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function isClosedStatus(string $status): bool
    {
        return in_array($status, config('tasks.closed_statuses', ['done']), true);
    }

    public function assigneeLabel(): string
    {
        if ($this->assignee_type === self::ASSIGNEE_AGENT && $this->assigneeAgent) {
            return $this->assigneeAgent->name.' (Agent)';
        }

        if ($this->assigneeUser) {
            return $this->assigneeUser->name;
        }

        return 'Unassigned';
    }

    public function relatedUrl(): ?string
    {
        if ($this->related_type === Schedule::class && $this->related_id) {
            return route('visit-reports', [
                'date_preset' => 'this_week',
                'open' => $this->related_id,
            ]);
        }

        if ($this->related_type === CareDetail::class && $this->related_id) {
            $auth = CareDetail::query()->find($this->related_id);
            if ($auth?->client_id) {
                return route('clients.show', ['id' => $auth->client_id, 'tab' => 'authorization']);
            }

            return route('authorizations');
        }

        if ($this->related_type === ComplianceForm::class) {
            if ($this->client_id) {
                return route('clients.show', ['id' => $this->client_id, 'tab' => 'documents']);
            }
            if ($this->employee_id) {
                return route('caregivers.show', $this->employee_id);
            }

            return route('compliance');
        }

        if ($this->related_type === Document::class && $this->related_id) {
            $document = Document::query()->find($this->related_id);
            if ($document?->documentable_type === Client::class && $document->documentable_id) {
                return route('clients.show', ['id' => $document->documentable_id, 'tab' => 'documents']);
            }
            if ($document?->documentable_type === Employee::class && $document->documentable_id) {
                return route('caregivers.show', $document->documentable_id);
            }
        }

        if ($this->related_type === BackgroundCheck::class && $this->employee_id) {
            return route('caregivers.show', $this->employee_id);
        }

        if ($this->client_id) {
            return route('clients.show', $this->client_id);
        }

        if ($this->employee_id) {
            return route('caregivers.show', $this->employee_id);
        }

        return null;
    }

    public function markDone(): void
    {
        $this->update([
            'status' => self::STATUS_DONE,
            'completed_at' => now(),
            'awaiting_approval' => false,
        ]);
    }

    public static function syncOverdueStatus(): void
    {
        // Product decision: Overdue is computed at display time via isOverdue() /
        // effectiveStatus() — do not overwrite board status (To do / In progress /
        // Done / Reopen) in the database. Priority elevation for overdue tasks is
        // handled by effectivePriority() so High badges and sorting stay obvious
        // without mutating the stored priority the user originally chose.
    }
}
