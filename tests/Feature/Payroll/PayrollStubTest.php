<?php

use App\Models\PayRecord;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function payrollTestStubFile(int $orgId, int $recordId): string
{
    $relative = "payroll/stubs/{$orgId}/stub-{$recordId}.pdf";
    $fullPath = storage_path('app/'.$relative);

    if (! is_dir(dirname($fullPath))) {
        mkdir(dirname($fullPath), 0755, true);
    }

    file_put_contents($fullPath, '%PDF-1.4 test pay stub');

    return $relative;
}

test('authorized admin sees pay stub pdf when stub exists', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['first_name' => 'Yousef', 'last_name' => 'Hassan']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);
    $stubPath = payrollTestStubFile($org->id, $record->id);

    $record->forceFill(['stub_path' => $stubPath])->saveQuietly();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.show', $record))
        ->assertOk()
        ->assertSee('Pay Stub PDF')
        ->assertSee(route('payroll.stub', $record), false);
});

test('authorized admin does not see raw stub path in payroll detail', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);
    $stubPath = payrollTestStubFile($org->id, $record->id);

    $record->forceFill(['stub_path' => $stubPath])->saveQuietly();

    $response = $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.show', $record));

    $response->assertOk();
    expect($response->getContent())->not->toContain($stubPath);
    expect($response->getContent())->not->toContain('storage/app/');
});

test('authorized admin sees no pay stub stored yet when stub missing', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.show', $record))
        ->assertOk()
        ->assertSee('No pay stub stored yet')
        ->assertDontSee('Pay Stub PDF');
});

test('user without payroll view permission does not see pay stub pdf action', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);
    $stubPath = payrollTestStubFile($org->id, $record->id);

    $record->forceFill(['stub_path' => $stubPath])->saveQuietly();

    $this->actingAsWithTwoFactor($staff)
        ->get(route('payroll.show', $record))
        ->assertForbidden();

    $this->actingAsWithTwoFactor($staff)
        ->get(route('payroll.stub', $record))
        ->assertForbidden();
});

test('cross-org user cannot access stub route', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $client = $this->createClient($orgB->id);
    $employee = $this->createEmployee($orgB->id);
    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);
    $record = payrollTestRecord($orgB->id, $employee->id, $client->id);
    $stubPath = payrollTestStubFile($orgB->id, $record->id);

    $record->forceFill(['stub_path' => $stubPath])->saveQuietly();

    $this->actingAsWithTwoFactor($adminA)
        ->get(route('payroll.stub', $record))
        ->assertNotFound();
});

test('path traversal or guessed stub path is rejected', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);

    $record->forceFill(['stub_path' => '../../../.env'])->saveQuietly();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.stub', $record))
        ->assertNotFound();

    $record->forceFill(['stub_path' => 'payroll/stubs/missing-file.pdf'])->saveQuietly();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.stub', $record))
        ->assertNotFound();
});

test('stub access route requires auth and two factor', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);
    $stubPath = payrollTestStubFile($org->id, $record->id);

    $record->forceFill(['stub_path' => $stubPath])->saveQuietly();

    $this->get(route('payroll.stub', $record))
        ->assertRedirect(route('signin'));

    $this->actingAs($admin)
        ->get(route('payroll.stub', $record))
        ->assertRedirect();
});

test('stub access creates audit log entry', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);
    $stubPath = payrollTestStubFile($org->id, $record->id);

    $record->forceFill(['stub_path' => $stubPath])->saveQuietly();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.stub', $record))
        ->assertOk();

    $this->assertDatabaseHas('payroll_audit_logs', [
        'pay_record_id' => $record->id,
        'action'        => 'stub_download',
        'actor_name'    => $admin->name,
    ]);
});

test('payroll detail page does not render what this screen does section', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.show', $record))
        ->assertOk()
        ->assertDontSee('What this screen does');
});

test('payroll index page does not render what this screen does section', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll', ['period' => '2026-05']))
        ->assertOk()
        ->assertDontSee('What this screen does');
});
