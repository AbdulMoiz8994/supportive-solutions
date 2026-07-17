<?php

use App\Models\BillingClaimAudit;
use App\Models\Client;
use App\Models\Intake;
use App\Models\PayRecord;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\LookupTableSeeder;

beforeEach(function () {
    seedModuleBasics();
    $this->seed(LookupTableSeeder::class);
});

test('full care workflow from intake through evv billing and payroll', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver', 'hourly_wage' => 15.00]);
    $caregiverUser = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $caregiver->update(['user_id' => $caregiverUser->id]);

    // 1. Intake
    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.store'), intakePayload([
            'first_name' => 'Workflow',
            'last_name' => 'Patient',
        ]))
        ->assertRedirect(route('intakes.index'));

    $intake = Intake::withoutGlobalScopes()->where('last_name', 'Patient')->first();
    expect($intake)->not->toBeNull();

    // 2. Convert to client
    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.convert', $intake->id))
        ->assertRedirect();

    $intake->refresh();
    $client = Client::withoutGlobalScopes()->find($intake->converted_client_id);
    expect($client)->not->toBeNull()
        ->and($client->first_name)->toBe('Workflow');

    // 3. Assign caregiver
    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.assign-caregiver', $client->id), [
            'employee_id' => $caregiver->id,
        ])
        ->assertRedirect();

    // 4. Schedule visit
    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), [
            'title' => 'Care Visit',
            'event_type' => Schedule::EVENT_CARE_VISIT,
            'client_id' => $client->id,
            'employee_id' => $caregiver->id,
            'date' => today()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '13:00',
            'timezone' => 'America/Detroit',
        ])
        ->assertRedirect();

    $schedule = Schedule::withoutGlobalScopes()
        ->where('client_id', $client->id)
        ->where('employee_id', $caregiver->id)
        ->first();
    expect($schedule)->not->toBeNull();

    // 5. EVV clock in/out
    $this->actingAsWithTwoFactor($caregiverUser)
        ->post(route('schedule.clock-in', $schedule->id), ['lat' => 42.33, 'lng' => -83.04])
        ->assertRedirect();

    $this->travel(4)->hours();

    $this->actingAsWithTwoFactor($caregiverUser)
        ->post(route('schedule.clock-out', $schedule->id), ['note' => 'Completed visit'])
        ->assertRedirect();

    $schedule->refresh();
    expect($schedule->status)->toBe(Schedule::STATUS_COMPLETED)
        ->and($schedule->total_hours)->toBeGreaterThan(0);

    // 6. Billing claim
    $claim = billingClaimAuditRecord($org->id, $client->id, [
        'employee_id' => $caregiver->id,
        'total_hours' => $schedule->total_hours,
        'visit_verification_status' => BillingClaimAudit::VISIT_VERIFIED,
    ]);

    expect($claim->client_id)->toBe($client->id);

    // 7. Payroll record
    $payRecord = payrollTestRecord($org->id, $caregiver->id, $client->id, [
        'hours' => $schedule->total_hours,
    ]);

    expect($payRecord->client_id)->toBe($client->id)
        ->and($payRecord->employee_id)->toBe($caregiver->id);

    // 8. Cross-module consistency
    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.show', $client->id))
        ->assertOk()
        ->assertSee('Workflow');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.show', $claim->id))
        ->assertOk();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.show', $payRecord->id))
        ->assertOk();

    $this->travelBack();
});

test('intake cannot be converted twice', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $intake = createTestIntake($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.convert', $intake->id))
        ->assertRedirect();

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.convert', $intake->id))
        ->assertRedirect()
        ->assertSessionHas('error');
});
