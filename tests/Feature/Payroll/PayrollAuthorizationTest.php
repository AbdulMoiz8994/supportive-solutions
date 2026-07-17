<?php

use App\Models\PayRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guest is redirected from payroll index', function () {
    $this->get(route('payroll'))
        ->assertRedirect(route('signin'));
});

test('authorized admin can access payroll index', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll', ['period' => '2026-05']))
        ->assertOk()
        ->assertSee('Payroll');
});

test('employee without permission cannot access payroll', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($employee)
        ->get(route('payroll'))
        ->assertForbidden();
});

test('admin can view payroll detail', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['first_name' => 'Yousef', 'last_name' => 'Hassan']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.show', $record))
        ->assertOk()
        ->assertSee('Yousef Hassan')
        ->assertSee('Pay calculation');
});

test('cross-org access to pay record is forbidden', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $client = $this->createClient($orgB->id);
    $employee = $this->createEmployee($orgB->id);
    $record = payrollTestRecord($orgB->id, $employee->id, $client->id);
    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);

    $this->actingAsWithTwoFactor($adminA)
        ->get(route('payroll.show', $record))
        ->assertNotFound();
});

test('operations staff without edit permission cannot update wage', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($staff)
        ->patch(route('payroll.update-wage', $record), ['hourly_wage' => 20])
        ->assertForbidden();
});

test('admin can update wage and gross recalculates', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['hourly_wage' => 15.00]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, ['hours' => 100, 'gross' => 1500]);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->patch(route('payroll.update-wage', $record), ['hourly_wage' => 16])
        ->assertRedirect();

    $record->refresh();
    expect((float) $record->rate)->toBe(16.00);
    expect((float) $record->gross)->toBe(1600.00);
});

test('invalid wage values are rejected', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->patch(route('payroll.update-wage', $record), ['hourly_wage' => 0.01])
        ->assertSessionHasErrors('hourly_wage');

    expect((float) $record->fresh()->rate)->toBe(15.00);
});

test('paid records cannot be edited', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, ['status' => PayRecord::STATUS_PAID]);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->patch(route('payroll.update-wage', $record), ['hourly_wage' => 20])
        ->assertForbidden();
});

test('super admin can build batch admin cannot', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    payrollReadyTestRecord($org->id, $employee->id, $client->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('payroll.build-batch'), ['period' => '2026-05'])
        ->assertForbidden();

    $this->actingAsWithTwoFactor($super)
        ->post(route('payroll.build-batch'), ['period' => '2026-05'])
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('super admin with single org in period auto-resolves organization for batch', function () {
    $org = $this->createOrganization(['name' => 'Demo Agency']);
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    payrollReadyTestRecord($org->id, $employee->id, $client->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->post(route('payroll.build-batch'), ['period' => '2026-05'])
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('search filter is sanitized against sql injection', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll', ['search' => "'; DROP TABLE pay_records; --"]))
        ->assertOk();
});

test('hold reason is escaped in output', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, [
        'status'      => PayRecord::STATUS_HELD,
        'hold_reason' => '<script>alert(1)</script>',
    ]);

    $response = $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.show', $record));

    $response->assertOk();
    expect($response->getContent())->not->toContain('<script>alert(1)</script>');
});

test('release hold requires permission', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, [
        'status'      => PayRecord::STATUS_HELD,
        'hold_reason' => 'Review',
    ]);
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($staff)
        ->post(route('payroll.release-hold', $record))
        ->assertForbidden();
});

test('wage update creates audit log entry', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->patch(route('payroll.update-wage', $record), ['hourly_wage' => 17.50]);

    $this->assertDatabaseHas('payroll_audit_logs', [
        'pay_record_id' => $record->id,
        'action'        => 'wage_update',
    ]);
});

test('authenticated user without 2fa is redirected away from payroll', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAs($admin)
        ->get(route('payroll'))
        ->assertRedirect();
});

test('mass assignment cannot set protected payroll status via fill', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $record = payrollTestRecord($org->id, $employee->id, $client->id, ['status' => PayRecord::STATUS_AWAITING_FORM]);

    $record->fill(['status' => PayRecord::STATUS_READY, 'gross' => 9999]);
    $record->save();

    expect($record->fresh()->status)->toBe(PayRecord::STATUS_AWAITING_FORM);
    expect((float) $record->fresh()->gross)->toBe(1620.00);
});
