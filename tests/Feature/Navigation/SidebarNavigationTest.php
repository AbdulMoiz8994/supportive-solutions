<?php

use App\Helpers\MenuHelper;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('sidebar renders primary module links for admin', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $response = $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk();

    foreach ([
        '/communications',
        '/directory',
        '/schedule',
        '/reports',
        '/visit-reports',
        '/tasks',
        '/forms',
        '/data-exploration',
        '/staff',
        '/settings',
    ] as $path) {
        $response->assertSee($path, false);
    }

    foreach (['Visit Reports', 'Tasks', 'Forms', 'Data Exploration'] as $label) {
        $response->assertSee($label, false);
    }
});

test('operations staff sees engagement and insights modules in sidebar', function () {
    $staff = $this->createUser(User::ROLE_STAFF);

    $response = $this->actingAsWithTwoFactor($staff)
        ->get(route('dashboard'))
        ->assertOk();

    foreach ([
        '/communications',
        '/directory',
        '/schedule',
        '/reports',
        '/visit-reports',
        '/tasks',
        '/forms',
        '/data-exploration',
    ] as $path) {
        $response->assertSee($path, false);
    }

    $response->assertDontSee('href="/settings"', false);
});

test('menu helper filters dashboard modules by permission', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAs($employee);

    $paths = collect(MenuHelper::getMenuGroups())
        ->flatMap(fn (array $group) => $group['items'])
        ->pluck('path');

    expect($paths->contains('/visit-reports'))->toBeFalse();
    expect($paths->contains('/tasks'))->toBeFalse();
});
