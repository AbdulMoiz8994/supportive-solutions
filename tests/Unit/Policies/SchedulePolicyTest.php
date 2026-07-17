<?php

use App\Models\Schedule;
use App\Models\User;
use App\Policies\SchedulePolicy;
use App\Services\ScheduleClockService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->policy = new SchedulePolicy(app(ScheduleClockService::class));
});

test('schedule policy allows employee to clock own schedule', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employeeUser = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $employee = $this->createEmployee($org->id, ['user_id' => $employeeUser->id]);
    $schedule = $this->createSchedule($org->id, $client->id, $employee->id);

    $scheduleModel = Schedule::withoutGlobalScopes()->find($schedule->id);

    expect($this->policy->clockIn($employeeUser, $scheduleModel))->toBeTrue();
});

test('schedule policy denies employee clocking another employees schedule', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employeeUser = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $this->createEmployee($org->id, ['user_id' => $employeeUser->id]);
    $otherEmployee = $this->createEmployee($org->id);
    $schedule = $this->createSchedule($org->id, $client->id, $otherEmployee->id);

    $scheduleModel = Schedule::withoutGlobalScopes()->find($schedule->id);

    expect($this->policy->clockIn($employeeUser, $scheduleModel))->toBeFalse();
});

test('schedule policy denies employee from creating schedules', function () {
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $this->createOrganization()->id]);

    expect($this->policy->create($employee))->toBeFalse();
});

test('schedule policy allows admin to manage schedules in same organization', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $schedule = $this->createSchedule($org->id, $client->id, $employee->id);

    $scheduleModel = Schedule::withoutGlobalScopes()->find($schedule->id);

    expect($this->policy->viewAny($admin))->toBeTrue()
        ->and($this->policy->view($admin, $scheduleModel))->toBeTrue()
        ->and($this->policy->update($admin, $scheduleModel))->toBeTrue()
        ->and($this->policy->delete($admin, $scheduleModel))->toBeTrue();
});
