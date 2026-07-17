<?php

use App\Models\PayRecord;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('guest cannot access payroll module', function () {
    $this->get(route('payroll'))->assertRedirect(route('signin'));
    $this->get(route('payroll.export', ['period' => '2026-05']))->assertRedirect(route('signin'));
});

test('employee without payroll permission cannot access payroll index', function () {
    $employee = $this->createUser(User::ROLE_EMPLOYEE);

    $this->actingAsWithTwoFactor($employee)
        ->get(route('payroll'))
        ->assertForbidden();
});

test('payroll index tab counts align with database records for period', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    payrollTestRecord($org->id, $this->createEmployee($org->id)->id, $client->id, ['status' => PayRecord::STATUS_READY]);
    payrollTestRecord($org->id, $this->createEmployee($org->id)->id, $client->id, ['status' => PayRecord::STATUS_HELD, 'hold_reason' => 'Review']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll', ['period' => '2026-05']))
        ->assertOk()
        ->assertSee('Ready for batch')
        ->assertSee('Held / review');
});

test('payroll wage update recalculates gross pay', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['hourly_wage' => 15]);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, [
        'hours' => 100,
        'rate' => 15,
        'gross' => 1500,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->patch(route('payroll.update-wage', $record), ['hourly_wage' => 20])
        ->assertRedirect();

    $record->refresh();
    expect((float) $record->rate)->toBe(20.0)
        ->and((float) $record->gross)->toBe(2000.0);
});

test('payroll wage update validates hourly wage bounds', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->patch(route('payroll.update-wage', $record), ['hourly_wage' => 0.01])
        ->assertSessionHasErrors(['hourly_wage']);

    $this->actingAsWithTwoFactor($admin)
        ->patchJson(route('payroll.update-wage', $record), ['hourly_wage' => 9999])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['hourly_wage']);
});

test('payroll apply hold persists reason and status', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, ['status' => PayRecord::STATUS_READY]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('payroll.apply-hold', $record), ['hold_reason' => 'Missing compliance form'])
        ->assertRedirect();

    $record->refresh();
    expect($record->status)->toBe(PayRecord::STATUS_HELD)
        ->and($record->hold_reason)->toBe('Missing compliance form');
});

test('payroll apply hold requires hold reason', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('payroll.apply-hold', $record), ['hold_reason' => ''])
        ->assertSessionHasErrors(['hold_reason']);
});

test('payroll show returns 404 for nonexistent pay record', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.show', 999999))
        ->assertNotFound();
});

test('admin cannot view payroll record from another organization', function () {
    $orgA = $this->createOrganization();
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $client = $this->createClient($orgA->id);
    $employee = $this->createEmployee($orgA->id);
    $record = payrollTestRecord($orgA->id, $employee->id, $client->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $this->actingAsWithTwoFactor($adminB)
        ->get(route('payroll.show', $record))
        ->assertNotFound();
});

test('pay record relationships resolve client and employee', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['first_name' => 'Pay', 'last_name' => 'Client']);
    $employee = $this->createEmployee($org->id, ['first_name' => 'Pay', 'last_name' => 'Caregiver']);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);

    expect($record->client->last_name)->toBe('Client')
        ->and($record->employee->last_name)->toBe('Caregiver')
        ->and($record->organization_id)->toBe($org->id);
});

test('payroll batch queue page loads for authorized user', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.batch-queue', ['period' => '2026-05']))
        ->assertOk();
});

test('payroll export returns csv for authorized user', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    payrollTestRecord($org->id, $this->createEmployee($org->id)->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.export', ['period' => '2026-05']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('paid pay record cannot have wage updated', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, ['status' => PayRecord::STATUS_PAID]);

    expect($admin->can('updateWage', $record))->toBeFalse();

    $this->actingAsWithTwoFactor($admin)
        ->patch(route('payroll.update-wage', $record), ['hourly_wage' => 25])
        ->assertForbidden();
});
