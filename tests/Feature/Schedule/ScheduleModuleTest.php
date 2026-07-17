<?php

use App\Events\ScheduleEventCancelled;
use App\Events\ScheduleEventCreated;
use App\Events\ScheduleEventUpdated;
use App\Helpers\MenuHelper;
use App\Models\ActivityLog;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function validSchedulePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Intake Assessment',
        'description' => 'Initial intake visit',
        'event_type' => Schedule::EVENT_INTAKE,
        'date' => today()->addDay()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '10:00',
        'timezone' => 'America/Detroit',
        'address' => '123 Main St',
    ], $overrides);
}

test('guest cannot access schedule pages', function () {
    $this->get(route('schedule.index'))->assertRedirect(route('signin'));
    $this->get(route('schedule.create'))->assertRedirect(route('signin'));
});

test('unauthorized authenticated user cannot access schedule module', function () {
    $org = $this->createOrganization();
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    $permission = \App\Models\Permission::where('slug', 'view_calendar')->first();
    $role = \App\Models\Role::where('name', User::ROLE_STAFF)->first();
    $role->permissions()->detach($permission->id);

    $this->actingAsWithTwoFactor($staff)
        ->get(route('schedule.index'))
        ->assertForbidden();
});

test('menu shows schedule item and calendar path', function () {
    $user = $this->createUser(User::ROLE_ADMIN);
    $this->actingAs($user);

    $item = collect(MenuHelper::getMenuGroups())
        ->flatMap(fn ($group) => $group['items'])
        ->firstWhere('name', 'Schedule');

    expect($item)->not->toBeNull()
        ->and($item['path'])->toBe('/schedule/board');
});

test('calendar route redirects to schedule month view', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('calendar'))
        ->assertRedirect(route('schedule.index', ['view' => 'month']));
});

test('authorized user can view schedule index', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $this->createSchedule($org->id, $client->id, $employee->id, ['title' => 'Visible Event']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('schedule.index'))
        ->assertOk()
        ->assertSee('Visible Event')
        ->assertSee('Calendar');
});

test('schedule store resolves organization from client when user has no organization', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $superAdmin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => null]);

    $this->actingAsWithTwoFactor($superAdmin)
        ->post(route('schedule.store'), validSchedulePayload([
            'event_type' => Schedule::EVENT_INTERNAL,
            'client_id' => $client->id,
            'employee_id' => $employee->id,
        ]))
        ->assertRedirect();

    $schedule = Schedule::withoutGlobalScopes()->latest('id')->first();

    expect($schedule->organization_id)->toBe($org->id);
});

test('authorized user can create a valid schedule event', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    Event::fake([ScheduleEventCreated::class]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), validSchedulePayload([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $schedule = Schedule::withoutGlobalScopes()->latest('id')->first();

    expect($schedule->title)->toBe('Intake Assessment')
        ->and($schedule->event_type)->toBe(Schedule::EVENT_INTAKE)
        ->and($schedule->organization_id)->toBe($org->id)
        ->and($schedule->created_by)->toBe($admin->id);

    Event::assertDispatched(ScheduleEventCreated::class);
});

test('schedule store accepts typed 12-hour time values', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), validSchedulePayload([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'start_time' => '09:15 AM',
            'end_time' => '10:45 AM',
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $schedule = Schedule::withoutGlobalScopes()->latest('id')->first();
    expect($schedule->start_time)->toBe('09:15:00')
        ->and($schedule->end_time)->toBe('10:45:00');
});

test('validation rejects missing title invalid event type and invalid dates', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), [
            'title' => '',
            'event_type' => 'invalid_type',
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'date' => 'not-a-date',
            'start_time' => '10:00',
            'end_time' => '09:00',
        ])
        ->assertSessionHasErrors(['title', 'event_type', 'date', 'start_time']);
});

test('validation rejects end time before start time', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), validSchedulePayload([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'start_time' => '14:00',
            'end_time' => '13:00',
        ]))
        ->assertSessionHasErrors(['end_time']);
});

test('schedule create form preserves typed times after validation failure', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->from(route('schedule.create'))
        ->post(route('schedule.store'), validSchedulePayload([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'start_time' => '9:00 AM',
            'end_time' => '8:00 AM',
        ]))
        ->assertRedirect(route('schedule.create'))
        ->assertSessionHasErrors(['end_time'])
        ->assertSessionHasInput('start_time', '9:00 AM')
        ->assertSessionHasInput('end_time', '8:00 AM');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('schedule.create'))
        ->assertOk()
        ->assertSee('value="09:00 AM"', false)
        ->assertSee('value="08:00 AM"', false);
});

test('schedule store accepts flexible typed time without leading zero', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), validSchedulePayload([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'start_time' => '9:00 AM',
            'end_time' => '1:00 PM',
        ]))
        ->assertRedirect();

    $schedule = Schedule::withoutGlobalScopes()->latest('id')->first();
    expect($schedule->start_time)->toBe('09:00:00')
        ->and($schedule->end_time)->toBe('13:00:00');
});

test('user cannot view update or delete another organizations schedule event', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);

    $clientA = $this->createClient($orgA->id);
    $employeeA = $this->createEmployee($orgA->id);
    $schedule = $this->createSchedule($orgA->id, $clientA->id, $employeeA->id, ['title' => 'Org A Event']);

    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $this->actingAsWithTwoFactor($adminB)
        ->get(route('schedule.show', $schedule->id))
        ->assertForbidden();

    $this->actingAsWithTwoFactor($adminB)
        ->put(route('schedule.update', $schedule->id), validSchedulePayload([
            'client_id' => $clientA->id,
            'employee_id' => $employeeA->id,
            'title' => 'Hacked',
            'status' => Schedule::STATUS_SCHEDULED,
        ]))
        ->assertForbidden();

    $this->actingAsWithTwoFactor($adminB)
        ->delete(route('schedule.destroy', $schedule->id))
        ->assertForbidden();
});

test('authorized user can update schedule event details', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $schedule = $this->createSchedule($org->id, $client->id, $employee->id, ['title' => 'Original Title']);

    Event::fake([ScheduleEventUpdated::class]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('schedule.update', $schedule->id), validSchedulePayload([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'title' => 'Updated Title',
            'status' => Schedule::STATUS_COMPLETED,
        ]))
        ->assertRedirect(route('schedule.show', $schedule->id));

    $schedule->refresh();

    expect($schedule->title)->toBe('Updated Title')
        ->and($schedule->status)->toBe(Schedule::STATUS_COMPLETED);

    Event::assertDispatched(ScheduleEventUpdated::class);
});

test('authorized user can cancel and delete schedule events', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $schedule = $this->createSchedule($org->id, $client->id, $employee->id);

    Event::fake([ScheduleEventCancelled::class]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.cancel', $schedule->id))
        ->assertRedirect(route('schedule.show', $schedule->id));

    expect($schedule->fresh()->status)->toBe(Schedule::STATUS_CANCELLED);
    Event::assertDispatched(ScheduleEventCancelled::class);

    $this->actingAsWithTwoFactor($admin)
        ->delete(route('schedule.destroy', $schedule->id))
        ->assertRedirect(route('schedule.index'));

    expect(Schedule::withoutGlobalScopes()->withTrashed()->find($schedule->id)->trashed())->toBeTrue();
});

test('search finds events by title client and employee', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'UniqueClient']);
    $employee = $this->createEmployee($org->id, ['first_name' => 'UniqueCaregiver']);
    $otherClient = $this->createClient($org->id, ['first_name' => 'Other']);

    $this->createSchedule($org->id, $client->id, $employee->id, [
        'title' => 'Alpha Intake Visit',
        'date' => today()->toDateString(),
    ]);
    $this->createSchedule($org->id, $otherClient->id, $employee->id, [
        'title' => 'Beta Follow Up',
        'date' => today()->toDateString(),
    ]);

    $this->actingAsWithTwoFactor($admin);

    $this->get(route('schedule.index', ['search' => 'Alpha Intake', 'view' => 'list']))
        ->assertOk()
        ->assertSee('Alpha Intake Visit')
        ->assertDontSee('Beta Follow Up');

    $this->get(route('schedule.index', ['search' => 'UniqueClient', 'view' => 'list']))
        ->assertOk()
        ->assertSee('Alpha Intake Visit');

    $this->get(route('schedule.index', ['search' => 'UniqueCaregiver', 'view' => 'list']))
        ->assertOk()
        ->assertSee('Alpha Intake Visit');
});

test('filters work by date range event type and status', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);

    $this->createSchedule($org->id, $client->id, $employee->id, [
        'title' => 'Intake Event',
        'event_type' => Schedule::EVENT_INTAKE,
        'status' => Schedule::STATUS_SCHEDULED,
        'date' => today()->toDateString(),
    ]);
    $this->createSchedule($org->id, $client->id, $employee->id, [
        'title' => 'Completed Visit',
        'event_type' => Schedule::EVENT_CARE_VISIT,
        'status' => Schedule::STATUS_COMPLETED,
        'date' => today()->addDays(3)->toDateString(),
    ]);

    $this->actingAsWithTwoFactor($admin);

    $this->get(route('schedule.index', [
        'event_type' => Schedule::EVENT_INTAKE,
        'from' => today()->toDateString(),
        'to' => today()->toDateString(),
        'range' => 'custom',
    ]))
        ->assertOk()
        ->assertSee('Intake Event')
        ->assertDontSee('Completed Visit');

    $this->get(route('schedule.index', [
        'status' => Schedule::STATUS_COMPLETED,
        'from' => today()->toDateString(),
        'to' => today()->addWeek()->toDateString(),
        'range' => 'custom',
    ]))
        ->assertOk()
        ->assertSee('Completed Visit')
        ->assertDontSee('Intake Event');
});

test('client linked event appears in client appointments tab', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'TabClient']);
    $employee = $this->createEmployee($org->id);
    $this->createSchedule($org->id, $client->id, $employee->id, ['title' => 'Client Tab Event']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.show', $client->id).'?tab=schedule')
        ->assertOk()
        ->assertSee('Client Tab Event')
        ->assertSee('Visits / Schedule');
});

test('client appointments tab loads linked events across schedule location scope', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $clientLocation = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $otherLocation = Location::create(['name' => 'Dearborn', 'state' => 'MI']);
    $client = $this->createClient($org->id, ['location_id' => $clientLocation->id, 'first_name' => 'Scoped']);
    $employee = $this->createEmployee($org->id, ['location_id' => $clientLocation->id]);

    $this->createSchedule($org->id, $client->id, $employee->id, [
        'title' => 'Cross Scope Event',
        'location_id' => $otherLocation->id,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $clientLocation->id])
        ->get(route('clients.show', $client->id).'?tab=schedule')
        ->assertOk()
        ->assertSee('Cross Scope Event')
        ->assertSee('Visits / Schedule');
});

test('employee linked event appears in employee schedule tab', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['first_name' => 'TabEmployee']);
    $this->createSchedule($org->id, $client->id, $employee->id, ['title' => 'Employee Tab Event']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('employees.show', $employee->id))
        ->assertOk()
        ->assertSee('Employee Tab Event');
});

test('audit log is written on schedule create update and delete', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $before = ActivityLog::count();

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), validSchedulePayload([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
        ]))
        ->assertRedirect();

    $schedule = Schedule::withoutGlobalScopes()->latest('id')->first();

    expect(ActivityLog::count())->toBe($before + 1);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('schedule.update', $schedule->id), validSchedulePayload([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'title' => 'Updated Audit Title',
            'status' => Schedule::STATUS_SCHEDULED,
        ]))
        ->assertRedirect();

    expect(ActivityLog::count())->toBe($before + 2);

    $this->actingAsWithTwoFactor($admin)
        ->delete(route('schedule.destroy', $schedule->id))
        ->assertRedirect();

    expect(ActivityLog::count())->toBe($before + 3);
});

test('blade output escapes malicious schedule title and description', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $payload = '<script>alert("xss")</script>';

    $schedule = $this->createSchedule($org->id, $client->id, $employee->id, [
        'title' => $payload,
        'description' => $payload,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('schedule.show', $schedule->id))
        ->assertOk()
        ->assertSee($payload)
        ->assertDontSee($payload, false);
});

test('sql injection like search input does not break filtering or leak records', function () {
    $org = $this->createOrganization();
    $orgB = $this->createOrganization(['name' => 'Other Org']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $otherClient = $this->createClient($orgB->id);
    $otherEmployee = $this->createEmployee($orgB->id);

    $this->createSchedule($org->id, $client->id, $employee->id, ['title' => 'Safe Event']);
    $this->createSchedule($orgB->id, $otherClient->id, $otherEmployee->id, ['title' => 'Secret Other Org']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('schedule.index', ['search' => "%' OR 1=1 --", 'view' => 'list']))
        ->assertOk()
        ->assertDontSee('Secret Other Org');
});

test('schedule policy enforces organization scoping for view', function () {
    $orgA = $this->createOrganization();
    $orgB = $this->createOrganization();
    $client = $this->createClient($orgA->id);
    $employee = $this->createEmployee($orgA->id);
    $schedule = $this->createSchedule($orgA->id, $client->id, $employee->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $scheduleModel = Schedule::withoutGlobalScopes()->find($schedule->id);
    $policy = new \App\Policies\SchedulePolicy(app(\App\Services\ScheduleClockService::class));

    expect($policy->view($adminB, $scheduleModel))->toBeFalse();
});

test('schedule ical export returns calendar file', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $this->createSchedule($org->id, $client->id, $employee->id, ['title' => 'iCal Event']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('schedule.ical'))
        ->assertOk()
        ->assertHeader('content-type', 'text/calendar; charset=utf-8');
});
