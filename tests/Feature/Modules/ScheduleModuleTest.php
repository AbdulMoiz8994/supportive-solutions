<?php

use App\Models\Client;
use App\Models\Communication;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => seedModuleBasics());

function moduleSchedulePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Module Visit',
        'event_type' => Schedule::EVENT_CARE_VISIT,
        'date' => today()->addDay()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '11:00',
        'timezone' => 'America/Detroit',
    ], $overrides);
}

test('guest cannot access schedule module', function () {
    $this->get(route('schedule.index'))->assertRedirect(route('signin'));
});

test('admin can create schedule event linked to client and caregiver', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), moduleSchedulePayload([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $schedule = Schedule::withoutGlobalScopes()->latest('id')->first();
    expect($schedule->client_id)->toBe($client->id)
        ->and($schedule->employee_id)->toBe($employee->id);
});

test('scheduling a care visit auto-assigns caregiver when client is unassigned', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['position' => 'Caregiver']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), moduleSchedulePayload([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(\App\Models\CaregiverAssignment::query()
        ->where('client_id', $client->id)
        ->where('employee_id', $employee->id)
        ->where('status', 'Active')
        ->exists())->toBeTrue();

    $client->refresh();
    expect($client->primary_caregiver?->id)->toBe($employee->id);
});

test('scheduling a care visit does not replace an existing caregiver assignment', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $assigned = $this->createEmployee($org->id, ['position' => 'Caregiver', 'first_name' => 'Assigned']);
    $scheduled = $this->createEmployee($org->id, ['position' => 'Caregiver', 'first_name' => 'Scheduled']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    \App\Models\CaregiverAssignment::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'employee_id' => $assigned->id,
        'status' => 'Active',
        'assigned_since' => now(),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), moduleSchedulePayload([
            'client_id' => $client->id,
            'employee_id' => $scheduled->id,
        ]))
        ->assertRedirect();

    expect(\App\Models\CaregiverAssignment::query()
        ->where('client_id', $client->id)
        ->where('employee_id', $assigned->id)
        ->where('status', 'Active')
        ->exists())->toBeTrue()
        ->and(\App\Models\CaregiverAssignment::query()
            ->where('client_id', $client->id)
            ->where('employee_id', $scheduled->id)
            ->where('status', 'Active')
            ->exists())->toBeFalse();
});

test('schedule store rejects end time before start time', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $before = Schedule::withoutGlobalScopes()->count();

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), moduleSchedulePayload([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'start_time' => '14:00',
            'end_time' => '10:00',
        ]))
        ->assertSessionHasErrors();

    expect(Schedule::withoutGlobalScopes()->count())->toBe($before);
});

test('schedule store accepts typed 12 hour times', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), moduleSchedulePayload([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'start_time' => '09:30 AM',
            'end_time' => '11:45 AM',
        ]))
        ->assertRedirect();

    $schedule = Schedule::withoutGlobalScopes()->latest('id')->first();
    expect($schedule->start_time)->toBe('09:30:00')
        ->and($schedule->end_time)->toBe('11:45:00');
});

test('admin can update schedule event', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $schedule = $this->createSchedule($org->id, $client->id, $employee->id, ['title' => 'Old Title']);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('schedule.update', $schedule->id), moduleSchedulePayload([
            'title' => 'Updated Title',
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'status' => Schedule::STATUS_SCHEDULED,
        ]))
        ->assertRedirect();

    expect($schedule->fresh()->title)->toBe('Updated Title');
});

test('admin can cancel schedule event', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $schedule = $this->createSchedule($org->id, $client->id, $employee->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.cancel', $schedule->id))
        ->assertRedirect();

    expect($schedule->fresh()->status)->toBe(Schedule::STATUS_CANCELLED);
});

test('schedule show returns 404 for missing event', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('schedule.show', 999999))
        ->assertNotFound();
});

test('schedule export returns downloadable response', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createSchedule($org->id, $client->id, $employee->id);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('schedule.export'))
        ->assertOk();
});
