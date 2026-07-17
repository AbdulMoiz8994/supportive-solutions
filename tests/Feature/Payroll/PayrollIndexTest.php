<?php

use App\Models\PayRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

test('payroll index is fully dynamic with summary counts from database', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    payrollTestRecord($org->id, $this->createEmployee($org->id)->id, $client->id, ['status' => PayRecord::STATUS_READY]);
    payrollTestRecord($org->id, $this->createEmployee($org->id)->id, $client->id, ['status' => PayRecord::STATUS_IN_GRACE]);

    $response = $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll', ['period' => '2026-05']));

    $response->assertOk()
        ->assertSee('Payroll')
        ->assertDontSee('132 ready')
        ->assertSee('Ready for batch');
});

test('payroll index filters by caregiver search', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $emp = $this->createEmployee($org->id, ['first_name' => 'Unique', 'last_name' => 'Caregiver']);
    payrollTestRecord($org->id, $emp->id, $client->id);
    payrollTestRecord($org->id, $this->createEmployee($org->id, ['first_name' => 'Other'])->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll', ['period' => '2026-05', 'search' => 'Unique']))
        ->assertOk()
        ->assertSee('Unique Caregiver')
        ->assertDontSee('Other');
});

test('payroll index respects status tab filter', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    payrollTestRecord($org->id, $this->createEmployee($org->id)->id, $client->id, ['status' => PayRecord::STATUS_HELD, 'hold_reason' => 'Review']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll', ['period' => '2026-05', 'status' => 'held']))
        ->assertOk()
        ->assertSee('Held - review');
});
