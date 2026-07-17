<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function awEmployeePayload(): array
{
    return [
        'aw_first_name' => 'Jane',
        'aw_last_name' => 'Doe',
        'aw_ssn' => '123456789',
        'aw_pay_rate' => 14.50,
        'aw_pay_type' => 'hourly',
    ];
}

test('failed accountants world setup is tracked and shown on batch queue', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://api.accountantsworld.test',
        'payroll.accountants_world_app_id' => 'c9c999aa4fc04b14a4f371aad354424e',
    ]);

    Http::fake([
        'https://api.accountantsworld.test/client/employee/import' => Http::response(['message' => 'Endpoint not found'], 404),
    ]);

    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id, ['first_name' => 'Sami', 'last_name' => 'Darwish']);
    $admin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('payroll.aw.create-employee'), array_merge(['employee_id' => $employee->id], awEmployeePayload()))
        ->assertRedirect()
        ->assertSessionHas('warning');

    $employee->refresh();

    expect($employee->payroll_system)->not->toBe('AccountantsWorld')
        ->and($employee->aw_setup_status)->toBe(Employee::AW_SETUP_FAILED)
        ->and($employee->aw_setup_error)->toContain('Endpoint not found')
        ->and($employee->aw_setup_payload)->toBeArray()
        ->and($employee->aw_setup_attempted_at)->not->toBeNull();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.batch-queue'))
        ->assertOk()
        ->assertSee('AccountantsWorld setup queue')
        ->assertSee('Sami Darwish')
        ->assertSee('Retry');
});

test('successful accountants world setup marks employee synced', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://api.accountantsworld.test',
        'payroll.accountants_world_app_id' => 'c9c999aa4fc04b14a4f371aad354424e',
    ]);

    Http::fake([
        'https://api.accountantsworld.test/client/employee/import' => Http::response([
            'success' => true,
            'numberImported' => 1,
            'numberFailed' => 0,
            'employeesModified' => [['employeeId' => 42]],
        ], 200),
    ]);

    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id);
    $admin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('payroll.aw.create-employee'), array_merge(['employee_id' => $employee->id], awEmployeePayload()))
        ->assertRedirect()
        ->assertSessionHas('success');

    $employee->refresh();

    expect($employee->payroll_system)->toBe('AccountantsWorld')
        ->and($employee->aw_setup_status)->toBe(Employee::AW_SETUP_SYNCED)
        ->and($employee->aw_employee_id)->toBe('42')
        ->and($employee->aw_setup_error)->toBeNull();
});

test('retry uses saved payload and clears failed status on success', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://api.accountantsworld.test',
        'payroll.accountants_world_app_id' => 'c9c999aa4fc04b14a4f371aad354424e',
    ]);

    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id, [
        'aw_setup_status' => Employee::AW_SETUP_FAILED,
        'aw_setup_error' => 'Endpoint not found',
        'aw_setup_payload' => [
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'ssn' => '123456789',
            'payRate' => 14.50,
            'payType' => 'hourly',
            'department' => 'Caregivers',
        ],
        'aw_setup_attempted_at' => now()->subHour(),
    ]);
    $admin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    Http::fake([
        'https://api.accountantsworld.test/client/employee/import' => Http::response([
            'success' => true,
            'numberImported' => 1,
            'numberFailed' => 0,
            'employeesModified' => [['employeeId' => 99]],
        ], 200),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('payroll.aw.retry-employee', $employee))
        ->assertRedirect()
        ->assertSessionHas('success');

    $employee->refresh();

    expect($employee->aw_setup_status)->toBe(Employee::AW_SETUP_SYNCED)
        ->and($employee->aw_employee_id)->toBe('99')
        ->and($employee->payroll_system)->toBe('AccountantsWorld');
});

test('mark synced without verify resolves failed setup without api call', function () {
    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id, [
        'aw_setup_status' => Employee::AW_SETUP_FAILED,
        'aw_setup_error' => 'Endpoint not found',
    ]);
    $admin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    Http::fake();

    $this->actingAsWithTwoFactor($admin)
        ->post(route('payroll.aw.resolve-employee', $employee), [
            'aw_employee_id' => 'MANUAL-1',
            'verify' => '0',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Http::assertNothingSent();

    $employee->refresh();

    expect($employee->aw_setup_status)->toBe(Employee::AW_SETUP_SYNCED)
        ->and($employee->aw_employee_id)->toBe('MANUAL-1')
        ->and($employee->payroll_system)->toBe('AccountantsWorld')
        ->and($employee->aw_setup_error)->toBeNull();
});

test('verify and mark synced confirms employee by aw id', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://api.accountantsworld.test',
        'payroll.accountants_world_app_id' => 'c9c999aa4fc04b14a4f371aad354424e',
    ]);

    Http::fake([
        'https://api.accountantsworld.test/client/employee/info/AW-PORTAL-7*' => Http::response(['name' => 'Jane Doe'], 200),
    ]);

    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id, [
        'aw_setup_status' => Employee::AW_SETUP_FAILED,
        'aw_setup_error' => 'Endpoint not found',
    ]);
    $admin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('payroll.aw.resolve-employee', $employee), [
            'aw_employee_id' => 'AW-PORTAL-7',
            'verify' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $employee->refresh();

    expect($employee->aw_setup_status)->toBe(Employee::AW_SETUP_SYNCED)
        ->and($employee->aw_employee_id)->toBe('AW-PORTAL-7')
        ->and($employee->payroll_system)->toBe('AccountantsWorld');
});

test('verify and mark synced can lookup employee by saved ssn', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://api.accountantsworld.test',
        'payroll.accountants_world_app_id' => 'c9c999aa4fc04b14a4f371aad354424e',
    ]);

    Http::fake([
        'https://api.accountantsworld.test/client/employee/list' => Http::response([
            'success' => true,
            'employeeList' => [['employeeId' => 888, 'ssn' => '123456789']],
        ], 200),
    ]);

    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id, [
        'aw_setup_status' => Employee::AW_SETUP_FAILED,
        'aw_setup_error' => 'Endpoint not found',
        'aw_setup_payload' => [
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'ssn' => '123456789',
            'payRate' => 14.50,
            'payType' => 'hourly',
        ],
    ]);
    $admin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('payroll.aw.resolve-employee', $employee), ['verify' => '1'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $employee->refresh();

    expect($employee->aw_setup_status)->toBe(Employee::AW_SETUP_SYNCED)
        ->and($employee->aw_employee_id)->toBe('888');
});

test('verify and mark synced keeps failed status when employee not found', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://api.accountantsworld.test',
        'payroll.accountants_world_app_id' => 'c9c999aa4fc04b14a4f371aad354424e',
    ]);

    Http::fake([
        'https://api.accountantsworld.test/client/employee/list' => Http::response(['message' => 'Not found'], 404),
    ]);

    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id, [
        'aw_setup_status' => Employee::AW_SETUP_FAILED,
        'aw_setup_error' => 'Endpoint not found',
        'aw_setup_payload' => ['ssn' => '123456789'],
    ]);
    $admin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('payroll.aw.resolve-employee', $employee), ['verify' => '1'])
        ->assertRedirect()
        ->assertSessionHas('warning');

    $employee->refresh();

    expect($employee->aw_setup_status)->toBe(Employee::AW_SETUP_FAILED)
        ->and($employee->payroll_system)->not->toBe('AccountantsWorld')
        ->and($employee->aw_setup_error_context)->toBe('verify')
        ->and($employee->aw_setup_http_status)->toBe(404)
        ->and($employee->aw_setup_error)->toContain('could not find this employee');
});

test('setup queue supports search filter and pagination', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $this->createEmployee($org->id, [
        'first_name' => 'Angela',
        'last_name' => 'Thompson',
        'aw_setup_status' => Employee::AW_SETUP_FAILED,
        'aw_setup_error' => 'AccountantsWorld could not find this employee (HTTP 404).',
        'aw_setup_error_context' => 'verify',
        'aw_setup_http_status' => 404,
        'aw_setup_attempted_at' => now(),
    ]);
    $this->createEmployee($org->id, [
        'first_name' => 'Other',
        'last_name' => 'Caregiver',
        'aw_setup_status' => Employee::AW_SETUP_FAILED,
        'aw_setup_error' => 'Create endpoint was not found (HTTP 404).',
        'aw_setup_error_context' => 'create',
        'aw_setup_attempted_at' => now()->subDay(),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.batch-queue', ['aw_search' => 'Angela']))
        ->assertOk()
        ->assertSee('Angela Thompson')
        ->assertSee('Verify failed')
        ->assertSee('AccountantsWorld could not find this employee')
        ->assertSee('Showing 1–1 of 1 pending');
});
