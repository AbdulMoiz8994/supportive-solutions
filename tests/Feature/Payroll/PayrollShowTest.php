<?php

use App\Models\ComplianceForm;
use App\Models\PayRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

test('payroll show displays calculation and lifecycle', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['first_name' => 'Amina', 'last_name' => 'Saleh', 'hourly_wage' => 15]);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    ComplianceForm::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'employee_id'     => $employee->id,
        'client_id'       => $client->id,
        'period'          => '2026-05',
        'status'          => ComplianceForm::STATUS_VERIFIED,
        'delivered_hours' => 100,
        'submitted_at'    => now()->subDays(15),
    ]);

    $record = payrollTestRecord($org->id, $employee->id, $client->id, [
        'hours' => 100,
        'gross' => 1500,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.show', $record))
        ->assertOk()
        ->assertSee('Amina Saleh')
        ->assertSee('Pay calculation')
        ->assertSee('Payout lifecycle');
});

test('paid record wage edit is unavailable at policy level', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, ['status' => PayRecord::STATUS_PAID]);

    expect($admin->can('updateWage', $record))->toBeFalse();
});
