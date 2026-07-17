<?php

use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = $this->createOrganization();
    $this->user = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $this->org->id]);
    $this->employee = $this->createEmployee($this->org->id, [
        'user_id'     => $this->user->id,
        'first_name'  => 'Robert',
        'last_name'   => 'Nguyen',
        'hourly_wage' => 15.00,
    ]);
    $this->client = $this->createClient($this->org->id, [
        'first_name' => 'Maria',
        'last_name'  => 'Hassan',
    ]);
    $this->employee->clients()->attach($this->client->id);
});

test('login returns a sanctum token', function () {
    $this->postJson('/api/login', [
        'email'    => $this->user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'email']]);
});

test('endpoints require authentication', function () {
    $this->getJson('/api/assignments')->assertUnauthorized();
});

test('non-caregiver accounts are rejected', function () {
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $this->org->id]);
    Sanctum::actingAs($admin);

    $this->getJson('/api/me')->assertForbidden();
});

test('me returns the caregiver profile', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.name', 'Robert Nguyen')
        ->assertJsonPath('data.hourly_wage', fn ($v) => (float) $v === 15.0);
});

test('assignments lists the caregivers clients', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/assignments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Maria Hassan');
});

test('caregiver can clock in and clock out, computing hours', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/visits/clock-in', [
        'client_id' => $this->client->id,
        'latitude'  => 42.31,
        'longitude' => -83.17,
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', Schedule::STATUS_CLOCKED_IN)
        ->assertJsonPath('data.client_name', 'Maria Hassan');

    $this->getJson('/api/visits/active')
        ->assertOk()
        ->assertJsonPath('data.status', Schedule::STATUS_CLOCKED_IN);

    $this->travel(2)->hours();

    $this->postJson('/api/visits/clock-out', ['notes' => 'Full visit completed.'])
        ->assertOk()
        ->assertJsonPath('data.status', Schedule::STATUS_COMPLETED)
        ->assertJsonPath('data.total_hours', fn ($v) => (float) $v === 2.0)
        ->assertJsonPath('data.evv_verified', true);

    $this->getJson('/api/visits/active')
        ->assertOk()
        ->assertJsonPath('data', null);
});

test('api clock out beyond duration cap does not auto verify evv', function () {
    Sanctum::actingAs($this->user);

    $schedule = $this->createSchedule($this->org->id, $this->client->id, $this->employee->id, [
        'status' => Schedule::STATUS_CLOCKED_IN,
        'date' => today()->subDays(2)->toDateString(),
        'actual_clock_in' => now()->subHours(30),
    ]);

    $this->postJson('/api/visits/clock-out', ['schedule_id' => $schedule->id])
        ->assertOk()
        ->assertJsonPath('data.status', Schedule::STATUS_COMPLETED)
        ->assertJsonPath('data.evv_verified', false)
        ->assertJsonPath('data.total_hours', fn ($v) => (float) $v > \App\Services\VisitReportService::maxVisitHours());

    $updated = Schedule::find($schedule->id);
    expect(data_get($updated->metadata, 'duration_flag.hours'))->not->toBeNull()
        ->and(app(\App\Services\VisitReportService::class)->resolveReportStatus($updated))
        ->toBe(\App\Services\VisitReportService::STATUS_NEEDS_REVIEW);
});

test('cannot clock in twice', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/visits/clock-in', ['client_id' => $this->client->id])->assertCreated();
    $this->postJson('/api/visits/clock-in', ['client_id' => $this->client->id])->assertStatus(409);
});

test('cannot clock in for an unassigned client', function () {
    Sanctum::actingAs($this->user);

    $stranger = $this->createClient($this->org->id, ['first_name' => 'Stranger']);

    $this->postJson('/api/visits/clock-in', ['client_id' => $stranger->id])->assertStatus(422);
});

test('schedule returns upcoming shifts', function () {
    Sanctum::actingAs($this->user);

    $this->createSchedule($this->org->id, $this->client->id, $this->employee->id, [
        'date'  => today()->addDay()->toDateString(),
        'title' => 'Upcoming Visit',
    ]);

    $this->getJson('/api/schedule')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Upcoming Visit');
});

test('pay history only shows the caregivers own records', function () {
    Sanctum::actingAs($this->user);

    payrollTestRecord($this->org->id, $this->employee->id, $this->client->id, ['gross' => 1620.00]);

    $other = $this->createEmployee($this->org->id, ['first_name' => 'Other']);
    payrollTestRecord($this->org->id, $other->id, $this->client->id, ['gross' => 999.00]);

    $this->getJson('/api/pay')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.gross', fn ($v) => (float) $v === 1620.0);
});
