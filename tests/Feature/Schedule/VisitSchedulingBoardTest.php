<?php

use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guest cannot access visit scheduling board', function () {
    $this->get(route('schedule.board'))->assertRedirect(route('signin'));
});

test('menu shows schedule label pointing to visit board', function () {
    $user = $this->createUser(User::ROLE_ADMIN);
    $this->actingAs($user);

    $item = collect(\App\Helpers\MenuHelper::getMenuGroups())
        ->flatMap(fn ($group) => $group['items'])
        ->firstWhere('name', 'Schedule');

    expect($item)->not->toBeNull()
        ->and($item['path'])->toBe('/schedule/board');
});

test('authorized user can view visit scheduling board with care visits', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Board', 'last_name' => 'Client']);
    $employee = $this->createEmployee($org->id, ['first_name' => 'Board', 'last_name' => 'Caregiver']);
    $visitDate = now()->startOfWeek()->addDay()->toDateString();

    $this->createSchedule($org->id, $client->id, $employee->id, [
        'title' => 'Board Care Visit',
        'event_type' => Schedule::EVENT_CARE_VISIT,
        'date' => $visitDate,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'start_at' => $visitDate.' 09:00:00',
        'end_at' => $visitDate.' 12:00:00',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('schedule.board'))
        ->assertOk()
        ->assertSee('Visit Scheduling', false)
        ->assertSee('Board Caregiver', false)
        ->assertSee('Board Client', false)
        ->assertSee('data-testid="visit-scheduling-board"', false);
});

test('admin can schedule visit from board and return to board', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $date = now()->startOfWeek()->addDays(2)->toDateString();

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), [
            'title' => 'Care visit — Quick Board',
            'event_type' => Schedule::EVENT_CARE_VISIT,
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'date' => $date,
            'start_time' => '10:00',
            'end_time' => '14:00',
            'timezone' => 'America/Detroit',
            'redirect_to' => 'board',
        ])
        ->assertRedirect(route('schedule.board', [
            'week' => now()->startOfWeek()->toDateString(),
        ]))
        ->assertSessionHas('success');

    expect(Schedule::withoutGlobalScopes()->where('title', 'Care visit — Quick Board')->exists())->toBeTrue();
});

test('clients appointments route redirects to visit board', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.appointments'))
        ->assertRedirect(route('schedule.board'));
});

test('work shifts route redirects to visit board', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('work-shifts'))
        ->assertRedirect(route('schedule.board'));
});

test('employee user is redirected from board to my visits', function () {
    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id);
    $user = $this->createUser(User::ROLE_EMPLOYEE, [
        'organization_id' => $org->id,
        'employee_id' => $employee->id,
    ]);

    $this->actingAsWithTwoFactor($user)
        ->get(route('schedule.board'))
        ->assertRedirect(route('schedule.index'));
});

test('admin can drag a visit card onto another caregiver and day', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $fromCaregiver = $this->createEmployee($org->id, ['first_name' => 'From']);
    $toCaregiver = $this->createEmployee($org->id, ['first_name' => 'To']);
    $originalDate = now()->startOfWeek()->addDay()->toDateString();
    $newDate = now()->startOfWeek()->addDays(2)->toDateString();

    $visit = $this->createSchedule($org->id, $client->id, $fromCaregiver->id, [
        'date' => $originalDate,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'start_at' => $originalDate.' 09:00:00',
        'end_at' => $originalDate.' 12:00:00',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->patchJson(route('schedule.board.move', $visit->id), [
            'employee_id' => $toCaregiver->id,
            'date' => $newDate,
        ])
        ->assertOk()
        ->assertJsonStructure(['message']);

    $visit->refresh();
    expect($visit->employee_id)->toBe($toCaregiver->id)
        ->and($visit->date->toDateString())->toBe($newDate);
});

test('dragging a visit onto a caregiver with an overlapping visit is rejected', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $clientA = $this->createClient($org->id, ['first_name' => 'A']);
    $clientB = $this->createClient($org->id, ['first_name' => 'B']);
    $fromCaregiver = $this->createEmployee($org->id, ['first_name' => 'From']);
    $busyCaregiver = $this->createEmployee($org->id, ['first_name' => 'Busy']);
    $date = now()->startOfWeek()->addDay()->toDateString();

    $this->createSchedule($org->id, $clientB->id, $busyCaregiver->id, [
        'date' => $date,
        'start_time' => '10:00:00',
        'end_time' => '13:00:00',
        'start_at' => $date.' 10:00:00',
        'end_at' => $date.' 13:00:00',
    ]);

    $visit = $this->createSchedule($org->id, $clientA->id, $fromCaregiver->id, [
        'date' => now()->startOfWeek()->addDays(2)->toDateString(),
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->patchJson(route('schedule.board.move', $visit->id), [
            'employee_id' => $busyCaregiver->id,
            'date' => $date,
        ])
        ->assertStatus(422)
        ->assertJson(['message' => 'This caregiver already has an overlapping visit at that time.']);

    expect($visit->fresh()->employee_id)->toBe($fromCaregiver->id);
});

test('scheduling an overlapping visit for the same caregiver is rejected on store', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $date = now()->startOfWeek()->addDay()->toDateString();

    $this->createSchedule($org->id, $client->id, $employee->id, [
        'date' => $date,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'start_at' => $date.' 09:00:00',
        'end_at' => $date.' 12:00:00',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), [
            'title' => 'Overlapping visit',
            'event_type' => Schedule::EVENT_CARE_VISIT,
            'client_id' => $this->createClient($org->id)->id,
            'employee_id' => $employee->id,
            'date' => $date,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'timezone' => 'America/Detroit',
        ])
        ->assertSessionHasErrors('employee_id');
});
