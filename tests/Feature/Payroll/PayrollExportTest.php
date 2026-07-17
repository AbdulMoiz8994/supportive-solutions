<?php

use App\Models\PayRecord;
use App\Models\User;
use App\Services\PayrollExportService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

test('export requires export_payroll permission', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.export', ['period' => '2026-05']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('export logs audit entry', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    payrollTestRecord($org->id, $this->createEmployee($org->id)->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.export', ['period' => '2026-05']));

    $this->assertDatabaseHas('payroll_audit_logs', ['action' => 'export']);
});

test('csv injection values are escaped in export service', function () {
    $service = app(PayrollExportService::class);

    expect($service->escapeCsvValue('=SUM(A1)'))->toBe("'=SUM(A1)");
    expect($service->escapeCsvValue('-100'))->toBe("'-100");
    expect($service->escapeCsvValue('@cmd'))->toBe("'@cmd");
    expect($service->escapeCsvValue('Normal Name'))->toBe('Normal Name');
});

test('employee without export permission cannot export payroll', function () {
    $org = $this->createOrganization();
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($staff)
        ->get(route('payroll.export', ['period' => '2026-05']))
        ->assertForbidden();
});
