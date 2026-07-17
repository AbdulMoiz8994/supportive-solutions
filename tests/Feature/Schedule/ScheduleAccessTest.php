<?php

use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('employee can only view own schedules for today', function () {
    $org = $this->createOrganization();
    $ownClient = $this->createClient($org->id, ['first_name' => 'Alice']);
    $otherClient = $this->createClient($org->id, ['first_name' => 'Bob']);

    $employeeUser = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $employee = $this->createEmployee($org->id, ['user_id' => $employeeUser->id, 'first_name' => 'Sarah']);

    $otherEmployee = $this->createEmployee($org->id, ['first_name' => 'Mike']);

    $this->createSchedule($org->id, $ownClient->id, $employee->id, [
        'date' => today()->toDateString(),
    ]);

    $this->createSchedule($org->id, $otherClient->id, $otherEmployee->id, [
        'date' => today()->toDateString(),
    ]);

    $this->createSchedule($org->id, $ownClient->id, $employee->id, [
        'date' => today()->addDay()->toDateString(),
    ]);

    $this->actingAsWithTwoFactor($employeeUser)
        ->get(route('schedule.index'))
        ->assertOk()
        ->assertSee('Alice')
        ->assertDontSee('Bob');
});

test('admin can view organization schedules', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['first_name' => 'Angela']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->createSchedule($org->id, $client->id, $employee->id);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('schedule.index'))
        ->assertOk()
        ->assertSee('Test Schedule Event');
});

test('admin can create schedules with scheduled status', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $response = $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), [
            'title' => 'Morning Visit',
            'event_type' => Schedule::EVENT_CARE_VISIT,
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'date' => today()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

    $schedule = Schedule::withoutGlobalScopes()->latest('id')->first();

    $response->assertRedirect(route('schedule.show', $schedule->id));

    expect($schedule->status)->toBe(Schedule::STATUS_SCHEDULED)
        ->and($schedule->employee_id)->toBe($employee->id);
});
