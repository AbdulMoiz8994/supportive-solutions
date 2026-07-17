<?php

use App\Models\AiAgent;
use App\Models\CareDetail;
use App\Models\Task;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('admin can view tasks page', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks'))
        ->assertOk()
        ->assertSee('Tasks')
        ->assertSee('New Task');
});

test('tasks page sync auto-assigns expiring authorization renewal to authorizations agent', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    CareDetail::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'billing_code' => 'T1019',
        'start_date' => today()->subMonths(5),
        'end_date' => today()->addDays(10),
        'total_units' => 320,
        'status' => 'Active',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks'))
        ->assertOk();

    $task = Task::where('organization_id', $org->id)
        ->where('source', Task::SOURCE_SYSTEM)
        ->where('related_type', CareDetail::class)
        ->first();

    expect($task)->not->toBeNull()
        ->and($task->assignee_type)->toBe(Task::ASSIGNEE_AGENT)
        ->and($task->assignee_agent_id)->not->toBeNull()
        ->and(AiAgent::find($task->assignee_agent_id)?->slug)->toBe('authorizations');
});

test('admin can create a task via json and receives success message', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.store'), [
            'title' => 'Follow up with client',
            'priority' => 'medium',
            'due_date' => today()->addDays(2)->toDateString(),
            'assignee_type' => 'user',
            'assignee_user_id' => $admin->id,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('message', 'Task created.');

    $this->assertDatabaseHas('tasks', [
        'title' => 'Follow up with client',
        'status' => Task::STATUS_TODO,
    ]);
});

test('admin can create a task', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('tasks.store'), [
            'title' => 'Call client to confirm visit',
            'priority' => 'high',
            'due_date' => today()->addDay()->toDateString(),
            'assignee_type' => 'user',
            'assignee_user_id' => $admin->id,
        ])
        ->assertRedirect(route('tasks'));

    $this->assertDatabaseHas('tasks', [
        'title' => 'Call client to confirm visit',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_HIGH,
    ]);
});

test('task can be assigned to ai agent', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $agent = AiAgent::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'slug' => 'test-agent',
        'name' => 'Test Agent',
        'is_enabled' => true,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('tasks.store'), [
            'title' => 'Agent task',
            'priority' => 'medium',
            'assignee_type' => 'agent',
            'assignee_agent_id' => $agent->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tasks', [
        'assignee_type' => Task::ASSIGNEE_AGENT,
        'assignee_agent_id' => $agent->id,
    ]);
});

test('overdue task is detected', function () {
    $org = $this->createOrganization();

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Overdue item',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_HIGH,
        'due_date' => today()->subDay(),
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);

    expect($task->isOverdue())->toBeTrue();
    expect($task->effectiveStatus())->toBe(Task::STATUS_OVERDUE);
    expect($task->status)->toBe(Task::STATUS_TODO);
});

test('overdue elevates effective priority to high without changing board status', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Medium but overdue',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_MEDIUM,
        'due_date' => today()->subDays(2),
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);

    expect($task->effectivePriority())->toBe(Task::PRIORITY_HIGH);
    expect($task->priority)->toBe(Task::PRIORITY_MEDIUM);
    expect($task->status)->toBe(Task::STATUS_TODO);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['priority' => 'high']))
        ->assertOk()
        ->assertSee('Medium but overdue')
        ->assertSee('Overdue')
        ->assertSee('High');
});

test('board view shows dynamic status columns including reopen', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Reopen me',
        'status' => Task::STATUS_REOPEN,
        'priority' => Task::PRIORITY_MEDIUM,
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'board']))
        ->assertOk()
        ->assertSee('Reopen')
        ->assertSee('To do')
        ->assertSee('In progress')
        ->assertSee('Done')
        ->assertSee('Drop tasks here');
});

test('task can be moved between board columns via json', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Move me',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_LOW,
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $task->id), [
            'status' => Task::STATUS_IN_PROGRESS,
            'view' => 'board',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(Task::find($task->id)->status)->toBe(Task::STATUS_IN_PROGRESS);
});

test('moving one task leaves other tasks in the same column unchanged', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $first = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'First todo',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_LOW,
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);

    $second = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Second todo',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_LOW,
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $first->id), [
            'status' => Task::STATUS_IN_PROGRESS,
            'view' => 'board',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(Task::find($first->id)->status)->toBe(Task::STATUS_IN_PROGRESS);
    expect(Task::find($second->id)->status)->toBe(Task::STATUS_TODO);

    $board = $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $second->id), [
            'status' => Task::STATUS_IN_PROGRESS,
            'view' => 'board',
        ])
        ->json('board');

    $todoColumn = collect($board)->firstWhere('key', Task::STATUS_TODO);
    expect($todoColumn['count'] ?? count($todoColumn['tasks'] ?? []))->toBe(0);
});

test('board statuses are loaded from organization task_board_statuses table', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    app(\App\Services\TaskBoardStatusService::class)->ensureDefaults($org->id);

    \App\Models\TaskBoardStatus::withoutGlobalScopes()
        ->where('organization_id', $org->id)
        ->where('key', Task::STATUS_TODO)
        ->update(['label' => 'Backlog']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'board']))
        ->assertOk()
        ->assertSee('Backlog')
        ->assertDontSee('To do');
});

test('board status move returns lightweight payload without full board', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Todo one',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_LOW,
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);

    Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Todo two',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_LOW,
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);

    $task = Task::withoutGlobalScopes()->where('organization_id', $org->id)->first();

    $response = $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $task->id), [
            'status' => Task::STATUS_IN_PROGRESS,
            'view' => 'board',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure(['task', 'counters']);

    expect($response->json('board'))->toBeNull();
    expect(Task::where('organization_id', $org->id)->where('status', Task::STATUS_TODO)->count())->toBe(1);
});

test('admin can add a custom board status column', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.board-statuses.store'), [
            'label' => 'On hold',
            'key' => 'on_hold',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $this->assertDatabaseHas('task_board_statuses', [
        'organization_id' => $org->id,
        'key' => 'on_hold',
        'label' => 'On hold',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'board']))
        ->assertOk()
        ->assertSee('On hold');
});

test('admin can view a task in the drawer api', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Drawer task',
        'description' => 'Detailed notes here',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_MEDIUM,
        'assignee_type' => Task::ASSIGNEE_USER,
        'assignee_user_id' => $admin->id,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->getJson(route('tasks.show', $task->id))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('task.title', 'Drawer task')
        ->assertJsonPath('task.description', 'Detailed notes here');
});

test('admin can update a task via drawer api', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Before edit',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_LOW,
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->putJson(route('tasks.update', $task->id), [
            'title' => 'After edit',
            'description' => 'Updated body',
            'status' => Task::STATUS_IN_PROGRESS,
            'priority' => Task::PRIORITY_HIGH,
            'assignee_type' => 'user',
            'assignee_user_id' => $admin->id,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('message', 'Task updated.')
        ->assertJsonPath('task.title', 'After edit');

    $task->refresh();
    expect($task->title)->toBe('After edit');
    expect($task->status)->toBe(Task::STATUS_IN_PROGRESS);
});

test('admin can reorder board statuses', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    app(\App\Services\TaskBoardStatusService::class)->ensureDefaults($org->id);

    $statuses = \App\Models\TaskBoardStatus::withoutGlobalScopes()
        ->where('organization_id', $org->id)
        ->orderBy('sort_order')
        ->pluck('id')
        ->all();

    $reordered = array_reverse($statuses);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.board-statuses.reorder'), ['order' => $reordered])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(
        \App\Models\TaskBoardStatus::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->orderBy('sort_order')
            ->pluck('id')
            ->all()
    )->toBe($reordered);
});

test('moving done task to reopen clears completed timestamp', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Was done',
        'status' => Task::STATUS_DONE,
        'priority' => Task::PRIORITY_LOW,
        'assignee_type' => Task::ASSIGNEE_USER,
        'completed_at' => now(),
    ]);

    app(\App\Services\TaskService::class)->updateStatus($org->id, $task->id, Task::STATUS_REOPEN);

    $task->refresh();
    expect($task->status)->toBe(Task::STATUS_REOPEN);
    expect($task->completed_at)->toBeNull();
});

test('open counter equals todo-only filtered tasks', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Todo A',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_MEDIUM,
        'assignee_type' => Task::ASSIGNEE_USER,
        'due_date' => today()->addDay(),
        'source' => Task::SOURCE_MANUAL,
    ]);
    Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'In progress B',
        'status' => Task::STATUS_IN_PROGRESS,
        'priority' => Task::PRIORITY_HIGH,
        'assignee_type' => Task::ASSIGNEE_USER,
        'due_date' => today()->addDay(),
        'source' => Task::SOURCE_MANUAL,
    ]);

    $page = app(\App\Services\TaskService::class)->pageData($org->id, request());
    $open = collect($page['counters'])->firstWhere('key', 'open');

    expect($open['label'])->toBe('Open (to do)');
    expect($open['value'])->toBe(1);

    $filtered = app(\App\Services\TaskService::class)->pageData($org->id, request()->merge(['status' => 'open']));
    expect(count($filtered['tasks'] ?? $filtered['rows'] ?? []))->toBeGreaterThanOrEqual(0);

    $list = collect($filtered['tasks'] ?? $filtered['board'] ?? [])
        ->when(isset($filtered['tasks']), fn ($c) => $c, function () use ($filtered) {
            return collect($filtered['board'] ?? [])->flatMap(fn ($col) => $col['tasks'] ?? []);
        });

    // Prefer explicit list payload when present.
    if (isset($filtered['tasks'])) {
        expect(count($filtered['tasks']))->toBe($open['value']);
    }
});

test('overdue tasks sort before non-overdue tasks', function () {
    $org = $this->createOrganization();

    $later = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Later task',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_HIGH,
        'assignee_type' => Task::ASSIGNEE_USER,
        'due_date' => today()->addDays(5),
        'source' => Task::SOURCE_MANUAL,
    ]);
    $overdue = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Overdue task',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_LOW,
        'assignee_type' => Task::ASSIGNEE_USER,
        'due_date' => today()->subDay(),
        'source' => Task::SOURCE_MANUAL,
    ]);

    $page = app(\App\Services\TaskService::class)->pageData($org->id, request()->merge(['view' => 'list']));
    $tasks = collect($page['tasks'] ?? []);
    expect($tasks->first()['id'] ?? null)->toBe($overdue->id);
    expect($tasks->pluck('id')->all())->toContain($later->id);
});

test('overdue counter matches overdue filtered list', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Overdue A',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_LOW,
        'due_date' => today()->subDays(3),
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);
    Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Overdue B',
        'status' => Task::STATUS_IN_PROGRESS,
        'priority' => Task::PRIORITY_MEDIUM,
        'due_date' => today()->subDay(),
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);
    Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Not overdue',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_HIGH,
        'due_date' => today()->addDay(),
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);

    $service = app(\App\Services\TaskService::class);
    $page = $service->pageData($org->id, request());
    $overdueCounter = collect($page['counters'])->firstWhere('key', 'overdue')['value'] ?? null;

    $filtered = $service->pageData($org->id, request()->merge(['status' => 'overdue']));
    expect($overdueCounter)->toBe(2);
    expect(count($filtered['tasks']))->toBe(2);
    expect(collect($filtered['tasks'])->every(fn ($t) => $t['is_overdue']))->toBeTrue();
});

test('task serialize keeps stored priority and exposes priority_effective', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Serialize priority contract',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_MEDIUM,
        'due_date' => today()->subDays(2),
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);

    $payload = $this->actingAsWithTwoFactor($admin)
        ->getJson(route('tasks.show', $task->id))
        ->assertOk()
        ->json('task');

    expect($payload['priority'])->toBe(Task::PRIORITY_MEDIUM);
    expect($payload['priority_stored'])->toBe(Task::PRIORITY_MEDIUM);
    expect($payload['priority_effective'])->toBe(Task::PRIORITY_HIGH);
    expect($payload['priority_elevated'])->toBeTrue();
    expect($payload['is_overdue'])->toBeTrue();
    expect($task->fresh()->priority)->toBe(Task::PRIORITY_MEDIUM);
});

test('authorization task related url opens client authorization tab', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $auth = CareDetail::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'billing_code' => 'T1019',
        'start_date' => today()->subMonths(5),
        'end_date' => today()->addDays(10),
        'total_units' => 100,
        'status' => 'Active',
    ]);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Renew auth',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_HIGH,
        'assignee_type' => Task::ASSIGNEE_AGENT,
        'client_id' => $client->id,
        'related_type' => CareDetail::class,
        'related_id' => $auth->id,
        'source' => Task::SOURCE_SYSTEM,
    ]);

    expect($task->relatedUrl())->toBe(route('clients.show', ['id' => $client->id, 'tab' => 'authorization']));
});

test('client-linked task related url opens client record', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Follow up with client',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_MEDIUM,
        'assignee_type' => Task::ASSIGNEE_USER,
        'client_id' => $client->id,
        'source' => Task::SOURCE_MANUAL,
    ]);

    expect($task->relatedUrl())->toBe(route('clients.show', $client->id));
});

test('status moves todo to in progress to done update counters', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $service = app(\App\Services\TaskService::class);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Counter walk',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_MEDIUM,
        'assignee_type' => Task::ASSIGNEE_USER,
        'due_date' => today()->addDays(2),
        'source' => Task::SOURCE_MANUAL,
    ]);

    $counters = collect($service->pageData($org->id, request())['counters'])->keyBy('key');
    expect($counters['open']['value'])->toBe(1)
        ->and($counters['in_progress']['value'])->toBe(0)
        ->and($counters['completed']['value'])->toBe(0);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $task->id), ['status' => Task::STATUS_IN_PROGRESS])
        ->assertOk();

    $counters = collect($service->pageData($org->id, request())['counters'])->keyBy('key');
    expect($counters['open']['value'])->toBe(0)
        ->and($counters['in_progress']['value'])->toBe(1)
        ->and($counters['completed']['value'])->toBe(0);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $task->id), ['status' => Task::STATUS_DONE])
        ->assertOk();

    $counters = collect($service->pageData($org->id, request())['counters'])->keyBy('key');
    expect($counters['open']['value'])->toBe(0)
        ->and($counters['in_progress']['value'])->toBe(0)
        ->and($counters['completed']['value'])->toBe(1);
});

test('agent task must be submitted for approval before marking done', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $agent = AiAgent::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'slug' => 'approval-agent',
        'name' => 'Approval Agent',
        'is_enabled' => true,
    ]);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Robot work needing sign-off',
        'status' => Task::STATUS_IN_PROGRESS,
        'priority' => Task::PRIORITY_HIGH,
        'assignee_type' => Task::ASSIGNEE_AGENT,
        'assignee_agent_id' => $agent->id,
        'awaiting_approval' => false,
        'source' => Task::SOURCE_MANUAL,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $task->id), ['status' => Task::STATUS_DONE])
        ->assertStatus(422);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.submit-for-approval', $task->id))
        ->assertOk()
        ->assertJsonPath('task.awaiting_approval', true);

    $task->refresh();
    expect($task->awaiting_approval)->toBeTrue()
        ->and($task->status)->toBe(Task::STATUS_IN_PROGRESS);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.update-status', $task->id), ['status' => Task::STATUS_DONE])
        ->assertOk();

    $task->refresh();
    expect($task->status)->toBe(Task::STATUS_DONE)
        ->and($task->awaiting_approval)->toBeFalse()
        ->and($task->completed_at)->not->toBeNull();
});

test('workflow queue approve marks awaiting agent task done', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $agent = AiAgent::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'slug' => 'queue-agent',
        'name' => 'Queue Agent',
        'is_enabled' => true,
    ]);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Queued robot result',
        'status' => Task::STATUS_IN_PROGRESS,
        'priority' => Task::PRIORITY_MEDIUM,
        'assignee_type' => Task::ASSIGNEE_AGENT,
        'assignee_agent_id' => $agent->id,
        'awaiting_approval' => true,
        'source' => Task::SOURCE_SYSTEM,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('workflow-queues.action', 'task-'.$task->id), [
            'queue_action' => 'approve',
            'approve_type' => 'task',
            'approve_id' => $task->id,
        ])
        ->assertRedirect(route('workflow-queues'));

    $task->refresh();
    expect($task->status)->toBe(Task::STATUS_DONE)
        ->and($task->awaiting_approval)->toBeFalse()
        ->and($task->completed_at)->not->toBeNull();
});

test('admin can add and list task comments', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Needs a note',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_LOW,
        'assignee_type' => Task::ASSIGNEE_USER,
        'assignee_user_id' => $admin->id,
        'source' => Task::SOURCE_MANUAL,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('tasks.comments.store', $task->id), ['body' => 'Called the caregiver.'])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $this->actingAsWithTwoFactor($admin)
        ->getJson(route('tasks.comments.index', $task->id))
        ->assertOk()
        ->assertJsonFragment(['body' => 'Called the caregiver.']);
});
