<?php

use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('employee can clock in and out own schedule using status string', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employeeUser = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $employee = $this->createEmployee($org->id, ['user_id' => $employeeUser->id]);
    $schedule = $this->createSchedule($org->id, $client->id, $employee->id);

    $this->actingAsWithTwoFactor($employeeUser)
        ->post(route('schedule.clock-in', $schedule->id), ['lat' => 42.33, 'lng' => -83.04])
        ->assertRedirect();

    $schedule->refresh();

    expect($schedule->status)->toBe(Schedule::STATUS_CLOCKED_IN)
        ->and($schedule->actual_clock_in)->not->toBeNull()
        ->and($schedule->clock_in_latitude)->toEqual(42.33);

    $this->travel(2)->hours();

    $this->actingAsWithTwoFactor($employeeUser)
        ->post(route('schedule.clock-out', $schedule->id), ['note' => 'Visit complete'])
        ->assertRedirect(route('schedule.index'));

    $schedule->refresh();

    expect($schedule->status)->toBe(Schedule::STATUS_COMPLETED)
        ->and($schedule->actual_clock_out)->not->toBeNull()
        ->and($schedule->total_hours)->toBeGreaterThan(0)
        ->and($schedule->visit_notes)->toBe(['note' => 'Visit complete']);

    $this->travelBack();
});

test('employee cannot clock another employees schedule', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employeeUser = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $this->createEmployee($org->id, ['user_id' => $employeeUser->id]);

    $otherEmployee = $this->createEmployee($org->id);
    $schedule = $this->createSchedule($org->id, $client->id, $otherEmployee->id);

    $this->actingAsWithTwoFactor($employeeUser)
        ->post(route('schedule.clock-in', $schedule->id))
        ->assertForbidden();

    $schedule->refresh();
    expect($schedule->actual_clock_in)->toBeNull();
});

test('invalid clock in flows are rejected', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employeeUser = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $employee = $this->createEmployee($org->id, ['user_id' => $employeeUser->id]);

    $futureSchedule = $this->createSchedule($org->id, $client->id, $employee->id, [
        'date' => today()->addDays(2)->toDateString(),
    ]);

    $this->actingAsWithTwoFactor($employeeUser)
        ->post(route('schedule.clock-in', $futureSchedule->id))
        ->assertSessionHasErrors('schedule');

    $activeSchedule = $this->createSchedule($org->id, $client->id, $employee->id, [
        'status' => Schedule::STATUS_CLOCKED_IN,
        'actual_clock_in' => now()->subHour(),
    ]);

    $this->actingAsWithTwoFactor($employeeUser)
        ->post(route('schedule.clock-in', $activeSchedule->id))
        ->assertSessionHasErrors('schedule');
});

test('invalid clock out flows are rejected', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employeeUser = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $employee = $this->createEmployee($org->id, ['user_id' => $employeeUser->id]);

    $notClockedIn = $this->createSchedule($org->id, $client->id, $employee->id);

    $this->actingAsWithTwoFactor($employeeUser)
        ->post(route('schedule.clock-out', $notClockedIn->id))
        ->assertSessionHasErrors('schedule');

    $completed = $this->createSchedule($org->id, $client->id, $employee->id, [
        'status' => Schedule::STATUS_COMPLETED,
        'actual_clock_in' => now()->subHours(2),
        'actual_clock_out' => now()->subHour(),
        'total_hours' => 1,
    ]);

    $this->actingAsWithTwoFactor($employeeUser)
        ->post(route('schedule.clock-out', $completed->id))
        ->assertSessionHasErrors('schedule');
});

test('clock in rejects invalid coordinates', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employeeUser = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $employee = $this->createEmployee($org->id, ['user_id' => $employeeUser->id]);
    $schedule = $this->createSchedule($org->id, $client->id, $employee->id);

    $this->actingAsWithTwoFactor($employeeUser)
        ->post(route('schedule.clock-in', $schedule->id), ['lat' => 120, 'lng' => -83.04])
        ->assertSessionHasErrors('lat');
});
