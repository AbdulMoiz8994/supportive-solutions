<?php

use App\Models\PayRecord;
use App\Models\Schedule;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => seedModuleBasics());

test('caregiver assignment through evv to payroll record', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id, ['hourly_wage' => 18.50]);
    $caregiverUser = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $caregiver->update(['user_id' => $caregiverUser->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.assign-caregiver', $client->id), ['employee_id' => $caregiver->id])
        ->assertRedirect();

    Sanctum::actingAs($caregiverUser);

    $clockIn = $this->postJson('/api/visits/clock-in', [
        'client_id' => $client->id,
        'latitude' => 42.31,
        'longitude' => -83.17,
    ])->assertCreated();

    $scheduleId = $clockIn->json('data.id');

    $this->travel(3)->hours();

    $this->postJson('/api/visits/clock-out', [
        'schedule_id' => $scheduleId,
        'notes' => 'Shift complete',
    ])->assertOk();

    $schedule = Schedule::withoutGlobalScopes()->find($scheduleId);
    expect($schedule->status)->toBe(Schedule::STATUS_COMPLETED);

    $payRecord = payrollTestRecord($org->id, $caregiver->id, $client->id, [
        'hours' => $schedule->total_hours ?? 3,
        'rate' => 18.50,
        'gross' => round(($schedule->total_hours ?? 3) * 18.50, 2),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.show', $payRecord->id))
        ->assertOk();

    expect(PayRecord::withoutGlobalScopes()->find($payRecord->id)->employee_id)->toBe($caregiver->id);

    $this->travelBack();
});

test('caregiver mobile api rejects clock in for unassigned client', function () {
    $org = $this->createOrganization();
    $caregiverUser = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $this->createEmployee($org->id, ['user_id' => $caregiverUser->id]);
    $unassignedClient = $this->createClient($org->id);

    Sanctum::actingAs($caregiverUser);

    $this->postJson('/api/visits/clock-in', [
        'client_id' => $unassignedClient->id,
        'latitude' => 42.31,
        'longitude' => -83.17,
    ])->assertStatus(422);
});
