<?php

use App\Helpers\MenuHelper;
use App\Models\Task;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('menu helper exposes all four dashboard modules in insights group', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAs($admin);

    $items = collect(MenuHelper::getMenuGroups())
        ->firstWhere('name', 'INSIGHTS')['items'] ?? [];

    $paths = collect($items)->pluck('path')->all();

    expect($paths)->toContain('/visit-reports', '/tasks', '/forms', '/data-exploration');
});

test('tasks board view includes drawer and manage statuses controls', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'Board UI task',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_MEDIUM,
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'board']))
        ->assertOk()
        ->assertSee('data-testid="task-detail-drawer"', false)
        ->assertSee('data-testid="manage-statuses-btn"', false)
        ->assertSee('data-testid="task-board-card"', false)
        ->assertSee('data-testid="board-statuses-modal"', false)
        ->assertSee('Board UI task');
});

test('tasks list view includes clickable rows with task ids', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $task = Task::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'title' => 'List UI task',
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_LOW,
        'assignee_type' => Task::ASSIGNEE_USER,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'list']))
        ->assertOk()
        ->assertSee('data-testid="task-list-row"', false)
        ->assertSee('data-task-id="'.$task->id.'"', false)
        ->assertSee('List UI task');
});

test('admin sees manage statuses button on board view', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('tasks', ['view' => 'board']))
        ->assertOk()
        ->assertSee('data-testid="manage-statuses-btn"', false);
});
