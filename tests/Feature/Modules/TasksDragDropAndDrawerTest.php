<?php

use App\Models\Task;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

function createBoardTask(int $orgId, array $attributes = []): Task
{
    return Task::withoutGlobalScopes()->create(array_merge([
        'organization_id' => $orgId,
        'title' => 'Board task '.uniqid(),
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_MEDIUM,
        'assignee_type' => Task::ASSIGNEE_USER,
    ], $attributes));
}

// ─── Board drag-and-drop client script (regression: edit-after-drag) ───────────

test('board drag script releases click suppression in onDrop finally block', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    createBoardTask($org->id, ['title' => 'Drag regression task']);

    $html = $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'board']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('releaseTaskClickSuppression()')
        ->toContain('async onDrop(columnKey, event)')
        ->toContain('finally')
        ->toContain('this.releaseTaskClickSuppression()');
});

test('board cards wire drag handlers and drawer click on the same element', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = createBoardTask($org->id, ['title' => 'Clickable card task']);

    $html = $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'board']))
        ->assertOk()
        ->assertSee('data-testid="task-board-card"', false)
        ->assertSee('draggable="true"', false)
        ->assertSee('@click="openTaskDrawer(task.id, $event)"', false)
        ->assertSee('@dragstart.stop="onDragStart($event, task)"', false)
        ->assertSee('@dragend.stop="onDragEnd()"', false)
        ->assertSee(':data-task-id="task.id"', false)
        ->assertSee('Clickable card task')
        ->getContent();

    expect($html)
        ->toContain('Clickable card task')
        ->toContain('\u0022id\u0022:'.$task->id);
});

test('board columns expose drop zones with status keys for drag targets', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $html = $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'board']))
        ->assertOk()
        ->assertSee('data-testid="task-board-column"', false)
        ->assertSee(':data-status-key="column.key"', false)
        ->assertSee('@drop.prevent.stop="onDrop(column.key, $event)"', false)
        ->assertSee('Drop tasks here')
        ->getContent();

    expect($html)
        ->toContain('\u0022key\u0022:\u0022todo\u0022')
        ->toContain('\u0022key\u0022:\u0022in_progress\u0022')
        ->toContain('\u0022key\u0022:\u0022done\u0022');
});

test('list view rows open drawer on click without board drag handlers', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = createBoardTask($org->id, ['title' => 'List drawer task']);

    $html = $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'list']))
        ->assertOk()
        ->assertSee('data-testid="task-list-row"', false)
        ->assertSee('data-task-id="'.$task->id.'"', false)
        ->assertSee('List drawer task')
        ->getContent();

    expect($html)->not->toContain('onDragStart($event, task)');
});

// ─── Full workflow: create → move → view → edit ───────────────────────────────

test('workflow create task move status then drawer shows updated board status', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $create = $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.store'), [
            'title' => 'Workflow pipeline task',
            'description' => 'Created for workflow test',
            'priority' => 'high',
            'due_date' => today()->addWeek()->toDateString(),
            'assignee_type' => 'user',
            'assignee_user_id' => $admin->id,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $taskId = $create->json('task_id') ?? Task::where('title', 'Workflow pipeline task')->value('id');
    expect($taskId)->not->toBeNull();

    $move = $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $taskId), [
            'status' => Task::STATUS_IN_PROGRESS,
            'view' => 'board',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('message', 'Task moved.')
        ->assertJsonPath('task.board_status', Task::STATUS_IN_PROGRESS)
        ->assertJsonPath('task.status', Task::STATUS_IN_PROGRESS);

    expect($move->json('board'))->toBeNull();

    $this->actingAsWithTwoFactor($admin)
        ->getJson(route('tasks.show', $taskId))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('task.title', 'Workflow pipeline task')
        ->assertJsonPath('task.description', 'Created for workflow test')
        ->assertJsonPath('task.board_status', Task::STATUS_IN_PROGRESS)
        ->assertJsonPath('task.priority', 'high');

    $this->actingAsWithTwoFactor($admin)
        ->putJson(route('tasks.update', $taskId), [
            'title' => 'Workflow pipeline task — edited',
            'description' => 'Updated after move',
            'status' => Task::STATUS_DONE,
            'priority' => 'medium',
            'assignee_type' => 'user',
            'assignee_user_id' => $admin->id,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('task.title', 'Workflow pipeline task — edited')
        ->assertJsonPath('task.board_status', Task::STATUS_DONE);

    $task = Task::find($taskId);
    expect($task->status)->toBe(Task::STATUS_DONE);
    expect($task->completed_at)->not->toBeNull();
});

test('workflow move through all default columns updates counters in status payload', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = createBoardTask($org->id, ['title' => 'Counter workflow task']);

    $statuses = [Task::STATUS_IN_PROGRESS, Task::STATUS_DONE, Task::STATUS_REOPEN, Task::STATUS_TODO];

    foreach ($statuses as $status) {
        $response = $this->actingAsWithTwoFactor($admin)
            ->postJson(route('tasks.update-status', $task->id), [
                'status' => $status,
                'view' => 'board',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('task.board_status', $status);

        expect($response->json('counters'))->toBeArray();
        expect($response->json('task.id'))->toBe($task->id);
    }

    $task->refresh();
    expect($task->status)->toBe(Task::STATUS_TODO);
});

test('moving task to done sets completed_at and drawer update preserves it', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = createBoardTask($org->id, ['title' => 'Complete me task']);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $task->id), [
            'status' => Task::STATUS_DONE,
            'view' => 'board',
        ])
        ->assertOk();

    $task->refresh();
    expect($task->completed_at)->not->toBeNull();

    $this->actingAsWithTwoFactor($admin)
        ->getJson(route('tasks.show', $task->id))
        ->assertOk()
        ->assertJsonPath('task.board_status', Task::STATUS_DONE)
        ->assertJsonPath('task.completed_at', fn ($value) => filled($value));
});

test('status move to same column returns success without changing task', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = createBoardTask($org->id, [
        'title' => 'Same column task',
        'status' => Task::STATUS_TODO,
    ]);

    $before = $task->updated_at;

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $task->id), [
            'status' => Task::STATUS_TODO,
            'view' => 'board',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('task.board_status', Task::STATUS_TODO);

    $task->refresh();
    expect($task->status)->toBe(Task::STATUS_TODO);
});

// ─── Drawer API detail shape ──────────────────────────────────────────────────

test('drawer api returns complete task shape for board editing', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = createBoardTask($org->id, [
        'title' => 'Drawer shape task',
        'description' => 'Full detail body',
        'status' => Task::STATUS_IN_PROGRESS,
        'priority' => Task::PRIORITY_HIGH,
        'due_date' => today()->addDays(3),
        'assignee_type' => Task::ASSIGNEE_USER,
        'assignee_user_id' => $admin->id,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->getJson(route('tasks.show', $task->id))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure([
            'task' => [
                'id',
                'title',
                'description',
                'status',
                'board_status',
                'status_label',
                'priority',
                'priority_label',
                'due_date',
                'due_date_raw',
                'assignee',
                'assignee_type',
                'is_overdue',
                'created_at',
            ],
        ])
        ->assertJsonPath('task.title', 'Drawer shape task')
        ->assertJsonPath('task.board_status', Task::STATUS_IN_PROGRESS)
        ->assertJsonPath('task.priority', 'high');
});

test('status move to done returns board_status done so drawer can hide mark complete', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = createBoardTask($org->id, [
        'title' => 'Mark complete refresh',
        'status' => Task::STATUS_IN_PROGRESS,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $task->id), [
            'status' => Task::STATUS_DONE,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('task.board_status', Task::STATUS_DONE)
        ->assertJsonPath('task.status', Task::STATUS_DONE)
        ->assertJsonPath('task.status_label', 'Done')
        ->assertJsonPath('task.is_overdue', false)
        ->assertJsonPath('task.completed_at', fn ($value) => filled($value));
});

test('tasks page syncs activeTask after move so mark complete can hide', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $html = $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('setActiveTask')
        ->toContain('refreshDrawerPhase')
        ->toContain('normalizeTaskPayload')
        ->toContain('isClosedBoardStatus')
        ->toContain('task-completed-banner')
        ->toContain('drawerPhase')
        ->toContain('task-awaiting-approval-panel')
        ->toContain('Approve &amp; mark done')
        ->toContain("drawerPhase === 'agent_awaiting'")
        ->toContain("drawerPhase === 'completed'");
});

test('agent submit then approve returns done without awaiting approval', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $agent = \App\Models\AiAgent::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'slug' => 'flow-agent',
        'name' => 'Flow Agent',
        'is_enabled' => true,
    ]);

    $task = createBoardTask($org->id, [
        'title' => 'Full agent approval flow',
        'status' => Task::STATUS_TODO,
        'assignee_type' => Task::ASSIGNEE_AGENT,
        'assignee_agent_id' => $agent->id,
        'awaiting_approval' => false,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.submit-for-approval', $task->id))
        ->assertOk()
        ->assertJsonPath('task.awaiting_approval', true)
        ->assertJsonPath('task.assignee_type', 'agent')
        ->assertJsonPath('task.board_status', Task::STATUS_IN_PROGRESS)
        ->assertJsonPath('task.completed_at', null);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $task->id), ['status' => Task::STATUS_DONE])
        ->assertOk()
        ->assertJsonPath('task.board_status', Task::STATUS_DONE)
        ->assertJsonPath('task.awaiting_approval', false)
        ->assertJsonPath('task.assignee_type', 'agent')
        ->assertJsonPath('task.completed_at', fn ($value) => filled($value));

    $task->refresh();
    expect($task->status)->toBe(Task::STATUS_DONE)
        ->and($task->awaiting_approval)->toBeFalse();
});

test('board statuses payload includes is_closed so done hides mark complete', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $page = app(\App\Services\TaskService::class)->pageData($org->id, request());
    $done = collect($page['boardStatuses'])->firstWhere('key', 'done');

    expect($done)->not->toBeNull()
        ->and($done['is_closed'])->toBeTrue();

    $todo = collect($page['boardStatuses'])->firstWhere('key', 'todo');
    expect($todo['is_closed'])->toBeFalse();

    $html = $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('is_closed')
        ->and(
            str_contains($html, '\u0022is_closed\u0022:true')
            || str_contains($html, '"is_closed":true')
            || str_contains($html, 'is_closed&quot;:true')
        )->toBeTrue();
});

test('drawer update returns refreshed counters and task payload', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = createBoardTask($org->id, ['title' => 'Drawer update counters']);

    $response = $this->actingAsWithTwoFactor($admin)
        ->putJson(route('tasks.update', $task->id), [
            'title' => 'Drawer update counters',
            'description' => 'Changed',
            'status' => Task::STATUS_IN_PROGRESS,
            'priority' => 'low',
            'assignee_type' => 'user',
            'assignee_user_id' => $admin->id,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Task updated.')
        ->assertJsonStructure(['task', 'counters']);

    expect($response->json('counters'))->toBeArray();
    expect(collect($response->json('counters'))->pluck('label')->all())->not->toBeEmpty();
});

// ─── Validation and authorization ─────────────────────────────────────────────

test('status move rejects invalid board status key', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = createBoardTask($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $task->id), [
            'status' => 'not_a_real_status',
            'view' => 'board',
        ])
        ->assertUnprocessable();
});

test('drawer show returns not found for task in another organization', function () {
    $orgA = $this->createOrganization();
    $orgB = $this->createOrganization();
    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);

    $task = createBoardTask($orgB->id, ['title' => 'Other org task']);

    $this->actingAsWithTwoFactor($adminA)
        ->getJson(route('tasks.show', $task->id))
        ->assertNotFound();
});

test('employee without view_tasks permission cannot access tasks page', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($employee)
        ->get(route('tasks'))
        ->assertForbidden();
});

test('employee cannot move task status via api', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $task = createBoardTask($org->id);

    $this->actingAsWithTwoFactor($employee)
        ->postJson(route('tasks.update-status', $task->id), [
            'status' => Task::STATUS_DONE,
            'view' => 'board',
        ])
        ->assertForbidden();
});

// ─── Filters and board rendering ──────────────────────────────────────────────

test('board status filter shows only tasks in selected column', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    createBoardTask($org->id, ['title' => 'Visible todo only', 'status' => Task::STATUS_TODO]);
    createBoardTask($org->id, ['title' => 'Hidden in progress task', 'status' => Task::STATUS_IN_PROGRESS]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'board', 'status' => Task::STATUS_TODO]))
        ->assertOk()
        ->assertSee('Visible todo only')
        ->assertDontSee('Hidden in progress task');
});

test('priority filter limits tasks shown on board', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    createBoardTask($org->id, ['title' => 'High priority visible', 'priority' => Task::PRIORITY_HIGH]);
    createBoardTask($org->id, ['title' => 'Low priority hidden', 'priority' => Task::PRIORITY_LOW]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'board', 'priority' => Task::PRIORITY_HIGH]))
        ->assertOk()
        ->assertSee('High priority visible')
        ->assertDontSee('Low priority hidden');
});

test('moved task appears on board in new column after page reload', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = createBoardTask($org->id, ['title' => 'Reload column task', 'status' => Task::STATUS_TODO]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $task->id), [
            'status' => Task::STATUS_IN_PROGRESS,
            'view' => 'board',
        ])
        ->assertOk();

    $html = $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'board']))
        ->assertOk()
        ->assertSee('Reload column task')
        ->getContent();

    expect($html)
        ->toContain('Reload column task')
        ->toContain('\u0022board_status\u0022:\u0022in_progress\u0022');
});

// ─── Manage permissions UI ────────────────────────────────────────────────────

test('board view includes drawer edit controls for users with manage_tasks', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'board']))
        ->assertOk()
        ->assertSee('data-testid="task-drawer-edit-btn"', false)
        ->assertSee('data-testid="manage-statuses-btn"', false)
        ->assertSee('startTaskDrawerEdit()', false)
        ->assertSee('saveTaskDrawer()', false);
});

test('board page embeds alpine tasksPage with board data for client state', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    createBoardTask($org->id, ['title' => 'Alpine state task']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'board']))
        ->assertOk()
        ->assertSee('x-data="tasksPage(', false)
        ->assertSee('suppressTaskClick', false)
        ->assertSee('openTaskDrawer', false)
        ->assertSee('moveTask(', false)
        ->assertSee('Alpine state task');
});
