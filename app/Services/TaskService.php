<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\CareDetail;
use App\Models\Client;
use App\Models\ComplianceForm;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TaskService
{
    public function __construct(
        protected TaskBoardStatusService $boardStatuses,
        protected AiAgentRegistryService $agentRegistry,
    ) {}

    public function pageData(?int $orgId, Request $request): array
    {
        $this->boardStatuses->ensureDefaults($orgId);
        Task::syncOverdueStatus();

        $filters = $this->parseFilters($request);
        $tasks = $this->fetchTasks($orgId, $filters);

        return [
            'title' => 'Tasks',
            'filters' => $filters,
            'view' => $request->input('view', 'list'),
            'counters' => $this->buildCounters($orgId, $filters),
            'tasks' => $tasks->map(fn (Task $t) => $this->serializeTask($orgId, $t)),
            'board' => $this->boardColumns($orgId, $tasks),
            'boardStatuses' => $this->boardStatusDefinitions($orgId),
            'manageBoardStatuses' => $this->boardStatuses->manageList($orgId),
            'canManageTasks' => auth()->user()?->hasPermission('manage_tasks') ?? false,
            'assignees' => $this->assigneeOptions($orgId),
            'clients' => $this->clientOptions($orgId),
            'caregivers' => $this->caregiverOptions($orgId),
            'priorityOptions' => $this->priorityOptions(),
            'statusOptions' => $this->statusOptions($orgId),
            'csrfToken' => csrf_token(),
        ];
    }

    public function store(?int $orgId, array $data, User $user): Task
    {
        return Task::create([
            'organization_id' => $orgId ?? $user->organization_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => Task::STATUS_TODO,
            'priority' => $data['priority'] ?? Task::PRIORITY_MEDIUM,
            'due_date' => $data['due_date'] ?? null,
            'assignee_type' => $data['assignee_type'] ?? Task::ASSIGNEE_USER,
            'assignee_user_id' => ($data['assignee_type'] ?? Task::ASSIGNEE_USER) === Task::ASSIGNEE_USER
                ? ($data['assignee_user_id'] ?? null)
                : null,
            'assignee_agent_id' => ($data['assignee_type'] ?? '') === Task::ASSIGNEE_AGENT
                ? ($data['assignee_agent_id'] ?? null)
                : null,
            'client_id' => $data['client_id'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,
            'source' => Task::SOURCE_MANUAL,
            'created_by' => $user->id,
        ]);
    }

    public function validBoardStatusKeys(?int $orgId): array
    {
        return $this->boardStatuses->validKeys($orgId);
    }

    public function updateStatus(?int $orgId, int $taskId, string $status): Task
    {
        if (! in_array($status, $this->boardStatuses->validKeys($orgId), true)) {
            throw new \InvalidArgumentException('Invalid task status.');
        }

        $task = $this->baseQuery($orgId)->findOrFail($taskId);

        if ($this->boardStatuses->isClosedStatus($orgId, $status)
            && $task->assignee_type === Task::ASSIGNEE_AGENT
            && ! $task->awaiting_approval
        ) {
            throw new \InvalidArgumentException(
                'Agent tasks must be submitted for approval before they can be marked Done.'
            );
        }

        $updates = ['status' => $status];

        if ($this->boardStatuses->isClosedStatus($orgId, $status)) {
            $updates['completed_at'] = $task->completed_at ?? now();
            $updates['awaiting_approval'] = false;
        } else {
            $updates['completed_at'] = null;
        }

        $task->update($updates);

        return $task->fresh(['assigneeUser', 'assigneeAgent', 'client', 'employee', 'creator']);
    }

    public function findTask(?int $orgId, int $taskId): Task
    {
        return $this->baseQuery($orgId)
            ->with(['assigneeUser', 'assigneeAgent', 'client', 'employee', 'creator'])
            ->findOrFail($taskId);
    }

    /**
     * @return array{ok: true, task: array}
     */
    public function taskDetailPayload(?int $orgId, Task $task): array
    {
        return [
            'ok' => true,
            'task' => $this->serializeTask($orgId, $task),
        ];
    }

    public function update(?int $orgId, int $taskId, array $data): Task
    {
        if (! in_array($data['status'], $this->boardStatuses->validKeys($orgId), true)) {
            throw new \InvalidArgumentException('Invalid task status.');
        }

        $task = $this->baseQuery($orgId)->findOrFail($taskId);

        if ($this->boardStatuses->isClosedStatus($orgId, $data['status'])
            && ($data['assignee_type'] ?? $task->assignee_type) === Task::ASSIGNEE_AGENT
            && ! $task->awaiting_approval
        ) {
            throw new \InvalidArgumentException(
                'Agent tasks must be submitted for approval before they can be marked Done.'
            );
        }

        $updates = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'],
            'assignee_type' => $data['assignee_type'] ?? Task::ASSIGNEE_USER,
            'assignee_user_id' => ($data['assignee_type'] ?? Task::ASSIGNEE_USER) === Task::ASSIGNEE_USER
                ? ($data['assignee_user_id'] ?? null)
                : null,
            'assignee_agent_id' => ($data['assignee_type'] ?? '') === Task::ASSIGNEE_AGENT
                ? ($data['assignee_agent_id'] ?? null)
                : null,
        ];

        if ($this->boardStatuses->isClosedStatus($orgId, $data['status'])) {
            $updates['completed_at'] = $task->completed_at ?? now();
            $updates['awaiting_approval'] = false;
        } else {
            $updates['completed_at'] = null;
        }

        $task->update($updates);

        return $task->fresh(['assigneeUser', 'assigneeAgent', 'client', 'employee', 'creator']);
    }

    /**
     * @return array{ok: true, message: string, task: array, counters: array}
     */
    public function taskUpdatePayload(?int $orgId, Task $task): array
    {
        return [
            'ok' => true,
            'message' => 'Task updated.',
            'task' => $this->serializeTask($orgId, $task),
            'counters' => $this->buildCounters($orgId),
        ];
    }

    /**
     * Lightweight payload for board drag-and-drop — avoids re-serializing the full board.
     *
     * @return array{ok: true, message: string, task: array, counters: array}
     */
    public function statusMovePayload(?int $orgId, Task $task): array
    {
        return [
            'ok' => true,
            'message' => 'Task moved.',
            'task' => $this->serializeTask($orgId, $task),
            'counters' => $this->buildCounters($orgId),
        ];
    }

    /**
     * @return array{boardStatuses: array, board: array, counters: array}
     */
    public function boardStructurePayload(?int $orgId, Request $request): array
    {
        $this->boardStatuses->ensureDefaults($orgId);
        $filters = $this->parseFilters($request);
        $tasks = $this->fetchTasks($orgId, $filters);

        return [
            'boardStatuses' => $this->boardStatusDefinitions($orgId),
            'manageBoardStatuses' => $this->boardStatuses->manageList($orgId),
            'board' => $this->boardColumns($orgId, $tasks),
            'counters' => $this->buildCounters($orgId),
        ];
    }

    public function createFromMissedVisit(Schedule $schedule): Task
    {
        $clientName = $schedule->client
            ? trim($schedule->client->first_name.' '.$schedule->client->last_name)
            : 'client';

        // A8: auto tasks route to the owning AI agent instead of landing
        // "Unassigned" — missed-visit follow-up calls belong to Communications.
        $agent = $this->owningAgent($schedule->organization_id, 'communications');

        return Task::firstOrCreate(
            [
                'organization_id' => $schedule->organization_id,
                'related_type' => Schedule::class,
                'related_id' => $schedule->id,
                'source' => Task::SOURCE_SYSTEM,
                'title' => "Reschedule missed visit for {$clientName}",
            ],
            [
                'description' => 'Caregiver did not show for a scheduled visit. Follow up to reschedule.',
                'status' => Task::STATUS_TODO,
                'priority' => Task::PRIORITY_HIGH,
                'due_date' => today()->addDay(),
                'assignee_type' => $agent ? Task::ASSIGNEE_AGENT : Task::ASSIGNEE_USER,
                'assignee_agent_id' => $agent?->id,
                'client_id' => $schedule->client_id,
                'employee_id' => $schedule->employee_id,
            ],
        );
    }

    public function createFromExpiringAuthorization(CareDetail $auth): ?Task
    {
        if (! $auth->needs_renewal) {
            return null;
        }

        $clientName = $auth->client
            ? trim($auth->client->first_name.' '.$auth->client->last_name)
            : 'client';

        $agent = $this->owningAgent($auth->organization_id, 'authorizations');

        $task = Task::firstOrCreate(
            [
                'organization_id' => $auth->organization_id,
                'related_type' => CareDetail::class,
                'related_id' => $auth->id,
                'source' => Task::SOURCE_SYSTEM,
                'status' => Task::STATUS_TODO,
            ],
            [
                'title' => "Renew {$clientName}'s authorization",
                'description' => 'Authorization expires on '.$auth->end_date?->format('M j, Y').'. Start renewal process.',
                'priority' => Task::PRIORITY_HIGH,
                'due_date' => today()->addDays(3),
                'assignee_type' => $agent ? Task::ASSIGNEE_AGENT : Task::ASSIGNEE_USER,
                'assignee_agent_id' => $agent?->id,
                'client_id' => $auth->client_id,
            ],
        );

        // Backfill (A8): older auto tasks created before the agent existed
        // stay "Unassigned" forever because firstOrCreate never updates.
        if ($agent && ! $task->wasRecentlyCreated && ! $task->assignee_user_id && ! $task->assignee_agent_id) {
            $task->update([
                'assignee_type' => Task::ASSIGNEE_AGENT,
                'assignee_agent_id' => $agent->id,
            ]);
        }

        return $task;
    }

    /**
     * ICHAT renews annually and needs the agency's MSP portal account, so the
     * monthly batch cannot re-run it automatically (client review D10). When a
     * caregiver's ICHAT is inside the renewal window this raises an idempotent
     * task for the Background Checks agent instead.
     */
    public function createFromIchatRenewal(\App\Models\BackgroundCheck $check): ?Task
    {
        if ($check->type !== 'ICHAT' || ! $check->next_due) {
            return null;
        }

        $caregiverName = $check->employee
            ? trim($check->employee->first_name.' '.$check->employee->last_name)
            : 'caregiver';

        $agent = $this->owningAgent($check->organization_id, 'background');

        $task = Task::firstOrCreate(
            [
                'organization_id' => $check->organization_id,
                'related_type' => \App\Models\BackgroundCheck::class,
                'related_id' => $check->id,
                'source' => Task::SOURCE_SYSTEM,
                'status' => Task::STATUS_TODO,
            ],
            [
                'title' => "Re-run ICHAT for {$caregiverName}",
                'description' => 'Annual ICHAT check is due '.$check->next_due->format('M j, Y').'. Run it via the ICHAT portal (MSP account) and record the result.',
                'priority' => Task::PRIORITY_HIGH,
                'due_date' => $check->next_due->copy()->subDays(14),
                'assignee_type' => $agent ? Task::ASSIGNEE_AGENT : Task::ASSIGNEE_USER,
                'assignee_agent_id' => $agent?->id,
                'employee_id' => $check->employee_id,
            ],
        );

        if ($agent && ! $task->wasRecentlyCreated && ! $task->assignee_user_id && ! $task->assignee_agent_id) {
            $task->update([
                'assignee_type' => Task::ASSIGNEE_AGENT,
                'assignee_agent_id' => $agent->id,
            ]);
        }

        return $task;
    }

    /**
     * Agent finished work — keep the task open and queue it for human approval.
     * Human marking Done on the Tasks page (or approving in Workflow Queues) completes it.
     */
    public function submitAgentResultForApproval(?int $orgId, int $taskId): Task
    {
        $task = $this->baseQuery($orgId)->findOrFail($taskId);

        if ($task->assignee_type !== Task::ASSIGNEE_AGENT) {
            throw new \InvalidArgumentException('Only agent-assigned tasks can be submitted for approval.');
        }

        $task->update([
            'status' => Task::STATUS_IN_PROGRESS,
            'awaiting_approval' => true,
            'completed_at' => null,
        ]);

        return $task->fresh(['assigneeUser', 'assigneeAgent', 'client', 'employee', 'creator']);
    }

    public function createFromOverdueCompliance(ComplianceForm $form): ?Task
    {
        if ($form->status !== ComplianceForm::STATUS_DUE) {
            return null;
        }

        $caregiverName = $form->employee
            ? trim($form->employee->first_name.' '.$form->employee->last_name)
            : 'caregiver';

        $periodLabel = $form->period_label ?: $form->period;
        $agent = $this->owningAgent($form->organization_id, 'compliance');

        $task = Task::firstOrCreate(
            [
                'organization_id' => $form->organization_id,
                'related_type' => ComplianceForm::class,
                'related_id' => $form->id,
                'source' => Task::SOURCE_SYSTEM,
                'status' => Task::STATUS_TODO,
            ],
            [
                'title' => "Follow up on overdue compliance form — {$caregiverName}",
                'description' => "Compliance form for {$periodLabel} is still Due. Contact the caregiver and collect the signed form.",
                'priority' => Task::PRIORITY_HIGH,
                'due_date' => today(),
                'assignee_type' => $agent ? Task::ASSIGNEE_AGENT : Task::ASSIGNEE_USER,
                'assignee_agent_id' => $agent?->id,
                'client_id' => $form->client_id,
                'employee_id' => $form->employee_id,
            ],
        );

        if ($agent && ! $task->wasRecentlyCreated && ! $task->assignee_user_id && ! $task->assignee_agent_id) {
            $task->update([
                'assignee_type' => Task::ASSIGNEE_AGENT,
                'assignee_agent_id' => $agent->id,
            ]);
        }

        return $task;
    }

    /** Enabled AI agent owning an automation area (A8 task routing). */
    private function owningAgent(?int $orgId, string $slug): ?AiAgent
    {
        if (! $orgId) {
            return null;
        }

        $agent = $this->agentRegistry->findBySlug($orgId, $slug);

        return ($agent && $agent->is_enabled) ? $agent : null;
    }

    public function syncAuthorizationTasks(?int $orgId): void
    {
        CareDetail::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->with('client')
            ->get()
            ->each(fn (CareDetail $auth) => $this->createFromExpiringAuthorization($auth));
    }

    public function syncComplianceTasks(?int $orgId): void
    {
        ComplianceForm::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('status', ComplianceForm::STATUS_DUE)
            ->with(['employee', 'client'])
            ->get()
            ->each(fn (ComplianceForm $form) => $this->createFromOverdueCompliance($form));
    }

    public function createFromExpiringDocument(Document $doc): ?Task
    {
        if (! $doc->isExpiringSoon()) {
            return null;
        }

        $doc->loadMissing('documentable');

        $agent = $this->owningAgent($doc->organization_id, 'document')
            ?? $this->owningAgent($doc->organization_id, 'compliance');

        $clientId = $doc->documentable_type === Client::class ? $doc->documentable_id : null;
        $employeeId = $doc->documentable_type === Employee::class ? $doc->documentable_id : null;

        $task = Task::firstOrCreate(
            [
                'organization_id' => $doc->organization_id,
                'related_type' => Document::class,
                'related_id' => $doc->id,
                'source' => Task::SOURCE_SYSTEM,
                'status' => Task::STATUS_TODO,
            ],
            [
                'title' => "Renew expiring document — {$doc->name}",
                'description' => 'Document expires on '.$doc->expires_at?->format('M j, Y').'. Review and renew before it lapses.',
                'priority' => Task::PRIORITY_HIGH,
                'due_date' => $doc->expires_at?->copy()->subDays(7) ?? today()->addDays(3),
                'assignee_type' => $agent ? Task::ASSIGNEE_AGENT : Task::ASSIGNEE_USER,
                'assignee_agent_id' => $agent?->id,
                'client_id' => $clientId,
                'employee_id' => $employeeId,
            ],
        );

        if ($agent && ! $task->wasRecentlyCreated && ! $task->assignee_user_id && ! $task->assignee_agent_id) {
            $task->update([
                'assignee_type' => Task::ASSIGNEE_AGENT,
                'assignee_agent_id' => $agent->id,
            ]);
        }

        return $task;
    }

    public function syncDocumentExpiryTasks(?int $orgId): void
    {
        Document::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>', today())
            ->whereDate('expires_at', '<=', today()->addDays(30))
            ->with('documentable')
            ->get()
            ->each(fn (Document $doc) => $this->createFromExpiringDocument($doc));
    }

    /**
     * @return list<array{id: int, body: string, user_name: string, created_at: string}>
     */
    public function listComments(?int $orgId, int $taskId): array
    {
        $task = $this->baseQuery($orgId)->findOrFail($taskId);

        return $task->comments()
            ->with('user:id,name')
            ->get()
            ->map(fn (TaskComment $c) => [
                'id' => $c->id,
                'body' => $c->body,
                'user_name' => $c->user?->name ?? 'Unknown',
                'created_at' => $c->created_at?->format('M j, Y g:i A'),
            ])
            ->all();
    }

    public function addComment(?int $orgId, int $taskId, User $user, string $body): TaskComment
    {
        $task = $this->baseQuery($orgId)->findOrFail($taskId);

        return TaskComment::create([
            'organization_id' => $task->organization_id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => $body,
        ]);
    }

    private function baseQuery(?int $orgId)
    {
        return Task::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId));
    }

    private function fetchTasks(?int $orgId, array $filters): Collection
    {
        $query = $this->baseQuery($orgId)
            ->with(['assigneeUser', 'assigneeAgent', 'client', 'employee', 'creator']);

        if ($filters['assignee_user_id'] ?? null) {
            $query->where('assignee_user_id', $filters['assignee_user_id']);
        }

        if ($filters['assignee_agent_id'] ?? null) {
            $query->where('assignee_agent_id', $filters['assignee_agent_id']);
        }

        $closedKeys = $this->boardStatuses->closedKeys($orgId);

        if ($filters['status'] ?? null) {
            if ($filters['status'] === 'open') {
                // "Open" means To do (not started), matching the counter label.
                $query->where('status', Task::STATUS_TODO);
            } elseif ($filters['status'] === 'completed') {
                $query->whereIn('status', $closedKeys);
            } elseif ($filters['status'] === Task::STATUS_OVERDUE) {
                $query->whereNotIn('status', $closedKeys)
                    ->whereDate('due_date', '<', today());
            } elseif ($filters['status'] === 'due_today') {
                $query->whereNotIn('status', $closedKeys)
                    ->whereDate('due_date', today());
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (($filters['due'] ?? null) === 'due_today') {
            $query->whereNotIn('status', $closedKeys)
                ->whereDate('due_date', today());
        } elseif (($filters['due'] ?? null) === 'overdue') {
            $query->whereNotIn('status', $closedKeys)
                ->whereDate('due_date', '<', today());
        }

        if ($filters['priority'] ?? null) {
            $priority = $filters['priority'];
            $closedKeys = $this->boardStatuses->closedKeys($orgId);
            // High filter includes overdue tasks (effective priority elevates to High).
            if ($priority === Task::PRIORITY_HIGH) {
                $query->where(function ($q) use ($closedKeys) {
                    $q->where('priority', Task::PRIORITY_HIGH)
                        ->orWhere(function ($overdue) use ($closedKeys) {
                            $overdue->whereNotIn('status', $closedKeys)
                                ->whereDate('due_date', '<', today());
                        });
                });
            } else {
                $query->where('priority', $priority)
                    ->where(function ($q) use ($closedKeys) {
                        $q->whereIn('status', $closedKeys)
                            ->orWhereNull('due_date')
                            ->orWhereDate('due_date', '>=', today());
                    });
            }
        }

        // Overdue first, then effective high priority, then soonest due date.
        // No hard row cap — counters and filtered lists must stay equal.
        return $query->get()->sortBy(function (Task $t) {
            $overdueRank = $t->isOverdue() ? 0 : 1;
            $priorityRank = match ($t->effectivePriority()) {
                Task::PRIORITY_HIGH => 1,
                Task::PRIORITY_MEDIUM => 2,
                default => 3,
            };
            $dueRank = $t->due_date?->format('Y-m-d') ?? '9999-12-31';

            return sprintf('%d-%d-%s-%010d', $overdueRank, $priorityRank, $dueRank, $t->id);
        })->values();
    }

    private function parseFilters(Request $request): array
    {
        $assigneeUserId = $request->integer('assignee_user_id') ?: null;
        $assigneeAgentId = $request->integer('assignee_agent_id') ?: null;

        $assignee = $request->input('assignee');
        if (is_string($assignee) && str_contains($assignee, ':')) {
            [$type, $id] = explode(':', $assignee, 2);
            $id = (int) $id;
            if ($type === 'user' && $id > 0) {
                $assigneeUserId = $id;
                $assigneeAgentId = null;
            } elseif ($type === 'agent' && $id > 0) {
                $assigneeAgentId = $id;
                $assigneeUserId = null;
            }
        }

        $due = $request->input('due') ?: null;
        if ($due === 'all') {
            $due = null;
        }

        return [
            'status' => $request->input('status') ?: null,
            'priority' => $request->input('priority') ?: null,
            'assignee' => $assignee ?: null,
            'assignee_user_id' => $assigneeUserId,
            'assignee_agent_id' => $assigneeAgentId,
            'due' => $due,
        ];
    }

    private function buildCounters(?int $orgId, array $filters = []): array
    {
        // Counters share sibling filters (assignee/priority/due) so click equality holds,
        // but ignore the counter's own status dimension.
        $baseFilters = array_merge($filters, [
            'status' => null,
            'due' => $filters['due'] ?? null,
        ]);
        $tasks = $this->fetchTasks($orgId, $baseFilters);
        $closedKeys = $this->boardStatuses->closedKeys($orgId);

        return [
            [
                'key' => 'open',
                'label' => 'Open (to do)',
                'value' => $tasks->where('status', Task::STATUS_TODO)->count(),
                'filter' => 'open',
            ],
            [
                'key' => Task::STATUS_IN_PROGRESS,
                'label' => 'In progress',
                'value' => $tasks->where('status', Task::STATUS_IN_PROGRESS)->count(),
                'filter' => Task::STATUS_IN_PROGRESS,
            ],
            [
                'key' => 'due_today',
                'label' => 'Due today',
                'value' => $tasks->whereNotIn('status', $closedKeys)
                    ->filter(fn (Task $t) => $t->due_date?->isToday())->count(),
                'filter' => 'due_today',
            ],
            [
                'key' => 'overdue',
                'label' => 'Overdue',
                'value' => $tasks->filter(fn (Task $t) => $t->isOverdue())->count(),
                'filter' => Task::STATUS_OVERDUE,
                'tone' => 'danger',
            ],
            [
                'key' => 'completed',
                'label' => 'Completed',
                'value' => $tasks->whereIn('status', $closedKeys)->count(),
                'filter' => 'completed',
            ],
        ];
    }

    private function boardColumns(?int $orgId, Collection $tasks): array
    {
        return collect($this->boardStatusDefinitions($orgId))->map(function (array $column) use ($orgId, $tasks) {
            $columnTasks = $tasks
                ->filter(fn (Task $t) => $t->status === $column['key'])
                ->map(fn (Task $t) => $this->serializeTask($orgId, $t))
                ->values()
                ->all();

            return array_merge($column, [
                'tasks' => $columnTasks,
                'count' => count($columnTasks),
            ]);
        })->values()->all();
    }

    private function boardStatusDefinitions(?int $orgId): array
    {
        return $this->boardStatuses->definitions($orgId);
    }

    private function serializeTask(?int $orgId, Task $task): array
    {
        $effective = $task->effectiveStatus();
        $storedPriority = $task->priority ?: Task::PRIORITY_MEDIUM;
        $effectivePriority = $task->effectivePriority();

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'about' => $task->client
                ? trim($task->client->first_name.' '.$task->client->last_name)
                : ($task->employee ? trim($task->employee->first_name.' '.$task->employee->last_name) : null),
            'assignee' => $task->assigneeLabel(),
            'assignee_type' => $task->assignee_type,
            'assignee_user_id' => $task->assignee_user_id,
            'assignee_agent_id' => $task->assignee_agent_id,
            'due_date' => $task->due_date?->format('M j, Y') ?? '—',
            'due_date_raw' => $task->due_date?->toDateString(),
            // Canonical stored DB priority (backward-compatible write/edit contract).
            'priority' => $storedPriority,
            'priority_stored' => $storedPriority,
            // Display/sort priority after overdue elevation.
            'priority_effective' => $effectivePriority,
            'priority_label' => ucfirst($effectivePriority),
            'priority_elevated' => $task->isOverdue() && $storedPriority !== Task::PRIORITY_HIGH,
            'status' => $effective,
            'board_status' => $task->status,
            'status_label' => $effective === Task::STATUS_OVERDUE
                ? 'Overdue'
                : $this->boardStatuses->label($orgId ?? $task->organization_id, $task->status),
            'is_overdue' => $task->isOverdue(),
            'awaiting_approval' => (bool) $task->awaiting_approval,
            'related_url' => $task->relatedUrl(),
            'client_id' => $task->client_id,
            'employee_id' => $task->employee_id,
            'source' => $task->source,
            'source_label' => $task->source === Task::SOURCE_SYSTEM ? 'System generated' : 'Manual',
            'created_at' => $task->created_at?->format('M j, Y g:i A'),
            'completed_at' => $task->completed_at?->format('M j, Y g:i A'),
            'created_by' => $task->creator?->name,
        ];
    }

    private function assigneeOptions(?int $orgId): array
    {
        $users = User::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['type' => 'user', 'id' => $u->id, 'name' => $u->name])
            ->all();

        $agents = AiAgent::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (AiAgent $a) => ['type' => 'agent', 'id' => $a->id, 'name' => $a->name.' (Agent)'])
            ->all();

        return array_merge($users, $agents);
    }

    private function clientOptions(?int $orgId): array
    {
        return Client::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(500)
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Client $c) => [
                'id' => $c->id,
                'name' => trim($c->first_name.' '.$c->last_name),
            ])
            ->all();
    }

    private function caregiverOptions(?int $orgId): array
    {
        return Employee::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(500)
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => trim($e->first_name.' '.$e->last_name),
            ])
            ->all();
    }

    private function priorityOptions(): array
    {
        return [
            ['value' => Task::PRIORITY_LOW, 'label' => 'Low'],
            ['value' => Task::PRIORITY_MEDIUM, 'label' => 'Medium'],
            ['value' => Task::PRIORITY_HIGH, 'label' => 'High'],
        ];
    }

    private function statusOptions(?int $orgId): array
    {
        $options = [
            ['value' => '', 'label' => 'All statuses'],
            ['value' => 'open', 'label' => 'Open'],
        ];

        foreach ($this->boardStatusDefinitions($orgId) as $status) {
            $options[] = ['value' => $status['key'], 'label' => $status['label']];
        }

        $options[] = ['value' => Task::STATUS_OVERDUE, 'label' => 'Overdue'];

        return $options;
    }
}
