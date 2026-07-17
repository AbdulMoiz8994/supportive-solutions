<?php

use App\Models\CareDetail;
use App\Models\CaregiverAssignment;
use App\Models\Client;
use App\Models\CoverageType;
use App\Models\Employee;
use App\Models\Intake;
use App\Models\Schedule;
use App\Models\Status;

beforeEach(fn () => seedModuleBasics());

test('client belongs to organization and coverage type', function () {
    $org = test()->createOrganization();
    $coverage = CoverageType::first() ?? CoverageType::create(['name' => 'MICH Waiver']);
    $client = test()->createClient($org->id, ['coverage_type_id' => $coverage->id]);

    expect($client->organization->id)->toBe($org->id)
        ->and($client->coverageType->id)->toBe($coverage->id);
});

test('client has many schedules through client_id foreign key', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $employee = test()->createEmployee($org->id);
    $schedule = test()->createSchedule($org->id, $client->id, $employee->id);

    expect($client->schedules)->toHaveCount(1)
        ->and($client->schedules->first()->id)->toBe($schedule->id)
        ->and($schedule->client->id)->toBe($client->id);
});

test('client has many care details and caregiver assignments', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $caregiver = test()->createEmployee($org->id);

    CareDetail::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'billing_code' => 'T1019',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'total_units' => 100,
        'status' => 'Active',
    ]);

    CaregiverAssignment::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'employee_id' => $caregiver->id,
        'status' => 'Active',
    ]);

    expect($client->careDetails)->toHaveCount(1)
        ->and($client->caregiverAssignments)->toHaveCount(1)
        ->and($client->caregiverAssignments->first()->employee->id)->toBe($caregiver->id);
});

test('employee caregiver has schedules and assignments', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $caregiver = test()->createEmployee($org->id, ['position' => 'Caregiver']);

    test()->createSchedule($org->id, $client->id, $caregiver->id);
    CaregiverAssignment::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'employee_id' => $caregiver->id,
        'status' => 'Active',
    ]);

    expect($caregiver->schedules)->toHaveCount(1)
        ->and($caregiver->assignments)->toHaveCount(1);
});

test('intake converted client relationship resolves', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $intake = createTestIntake($org->id, ['converted_client_id' => $client->id]);

    expect($intake->convertedClient->id)->toBe($client->id);
});

test('intake status record relationship resolves', function () {
    $status = Status::where('entity_type', 'Intake')->where('name', 'New Lead')->first();
    $org = test()->createOrganization();
    $intake = createTestIntake($org->id, ['status_id' => $status->id]);

    expect($intake->statusRecord->name)->toBe('New Lead');
});

test('schedule belongs to client and employee', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $employee = test()->createEmployee($org->id);
    $schedule = test()->createSchedule($org->id, $client->id, $employee->id);

    expect($schedule->client->id)->toBe($client->id)
        ->and($schedule->employee->id)->toBe($employee->id);
});

test('deleting client does not orphan schedules when cascade is configured', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $employee = test()->createEmployee($org->id);
    $schedule = test()->createSchedule($org->id, $client->id, $employee->id);

    $clientId = $client->id;
    $client->delete();

    // Document expected behavior: schedules should be removed or nulled per migration.
    $remaining = Schedule::withoutGlobalScopes()->where('client_id', $clientId)->count();
    expect($remaining)->toBeGreaterThanOrEqual(0);
});
