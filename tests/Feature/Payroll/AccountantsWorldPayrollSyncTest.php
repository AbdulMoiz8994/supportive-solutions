<?php

use App\Models\PayRecord;
use App\Models\PayrollBatch;
use App\Models\User;
use App\Services\Payroll\AccountantsWorldPayrollSyncService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

test('payroll sync service maps batch hours into aw time data and submits update', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://dev-api.payrollrelief.com/integration',
        'payroll.accountants_world_app_id' => 'test-app-id',
        'payroll.accountants_world_pay_schedule_id' => 12,
        'payroll.accountants_world_default_pay_type_code' => 'REG',
    ]);

    Http::fake([
        'https://dev-api.payrollrelief.com/integration/payroll/GetNextPayrollData/12' => Http::response([
            'keyData' => [
                'payrollId' => 501,
                'payPeriod' => [
                    'startDate' => '2026-05-01T00:00:00',
                    'endDate' => '2026-05-31T23:59:59',
                ],
            ],
            'timeData' => [[
                'empId' => 42,
                'empName' => 'Jane Doe',
                'payTypes' => [['payTypeCode' => 'REG', 'hours' => 0, 'amount' => 0]],
            ]],
        ], 200),
        'https://dev-api.payrollrelief.com/integration/payroll/UpdatePayrollData' => Http::response([
            'success' => true,
            'messages' => [],
        ], 200),
        'https://dev-api.payrollrelief.com/integration/payroll/PayrollDetails/*' => Http::response([
            'payrollId' => 501,
            'scheduleName' => 'Biweekly',
            'periodBeginDate' => '2026-05-01T00:00:00',
            'periodEndDate' => '2026-05-31T23:59:59',
        ], 200),
        'https://dev-api.payrollrelief.com/integration/payroll/PayrollPayStubs/501' => Http::response([
            ['paycheckID' => 9001, 'empID' => 42, 'grossPay' => 900, 'netPay' => 750],
        ], 200),
    ]);

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['aw_employee_id' => '42']);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);
    $record = payrollReadyTestRecord($org->id, $employee->id, $client->id, [
        'hours' => 60,
        'rate' => 15,
        'gross' => 900,
    ]);

    $batch = PayrollBatch::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'period_key' => '2026-05',
        'build_date' => '2026-06-03',
        'pay_date' => '2026-06-06',
        'record_count' => 1,
        'total_gross' => 900,
        'built_by' => $super->id,
        'built_at' => now(),
        'status' => 'built',
        'approval_status' => 'approved',
    ]);

    $record->batch_id = $batch->id;
    $record->saveQuietly();
    $batch->load('payRecords.employee');

    $result = app(AccountantsWorldPayrollSyncService::class)->syncBatch($batch, $super);

    expect($result['success'])->toBeTrue()
        ->and($result['payroll_id'])->toBe(501)
        ->and($result['pay_stub_count'])->toBe(1)
        ->and($batch->fresh()->approval_status)->toBe('exported')
        ->and($batch->fresh()->aw_payroll_id)->toBe(501)
        ->and($batch->fresh()->aw_payroll_meta['payrollDetailsVerified'])->toBeTrue()
        ->and($record->fresh()->exported_at)->not->toBeNull();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/payroll/UpdatePayrollData')) {
            return false;
        }

        $payload = $request->data();
        $hours = $payload['timeData'][0]['payTypes'][0]['hours'] ?? null;
        $amount = $payload['timeData'][0]['payTypes'][0]['amount'] ?? null;

        return (float) $hours === 60.0 && (float) $amount === 900.0;
    });
});

test('payroll sync service fails when caregiver lacks aw employee id', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);
    $record = payrollReadyTestRecord($org->id, $employee->id, $client->id);

    $batch = PayrollBatch::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'period_key' => '2026-05',
        'build_date' => '2026-06-03',
        'pay_date' => '2026-06-06',
        'record_count' => 1,
        'total_gross' => 1620,
        'built_by' => $super->id,
        'built_at' => now(),
        'status' => 'built',
        'approval_status' => 'approved',
    ]);

    $record->batch_id = $batch->id;
    $record->saveQuietly();
    $batch->load('payRecords.employee');

    $result = app(AccountantsWorldPayrollSyncService::class)->syncBatch($batch, $super);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('AccountantsWorld employee ID');
});

test('approved batch export syncs to accountants world via payroll api', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://dev-api.payrollrelief.com/integration',
        'payroll.accountants_world_app_id' => 'test-app-id',
        'payroll.accountants_world_pay_schedule_id' => 12,
    ]);

    Http::fake([
        'https://dev-api.payrollrelief.com/integration/payroll/GetNextPayrollData/12' => Http::response([
            'keyData' => ['payrollId' => 777, 'payPeriod' => ['startDate' => '2026-05-01T00:00:00', 'endDate' => '2026-05-31T23:59:59']],
            'timeData' => [[
                'empId' => 55,
                'payTypes' => [['payTypeCode' => 'REG', 'hours' => 0, 'amount' => 0]],
            ]],
        ], 200),
        'https://dev-api.payrollrelief.com/integration/payroll/UpdatePayrollData' => Http::response(['success' => true, 'messages' => []], 200),
        'https://dev-api.payrollrelief.com/integration/payroll/PayrollDetails/*' => Http::response(['payrollId' => 777], 200),
        'https://dev-api.payrollrelief.com/integration/payroll/PayrollPayStubs/777' => Http::response([], 200),
    ]);

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['aw_employee_id' => '55']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollReadyTestRecord($org->id, $employee->id, $client->id);

    $batch = PayrollBatch::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'period_key' => '2026-05',
        'build_date' => '2026-06-03',
        'pay_date' => '2026-06-06',
        'record_count' => 1,
        'total_gross' => $record->gross,
        'built_by' => $admin->id,
        'built_at' => now(),
        'status' => 'built',
        'approval_status' => 'approved',
    ]);

    $record->batch_id = $batch->id;
    $record->saveQuietly();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.batch.export', $batch->id))
        ->assertRedirect(route('payroll.batch-queue'))
        ->assertSessionHas('success');

    expect($batch->fresh()->approval_status)->toBe('exported')
        ->and($batch->fresh()->aw_payroll_id)->toBe(777);
});

test('batch export csv backup still works with format query param', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['aw_employee_id' => '55']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $record = payrollReadyTestRecord($org->id, $employee->id, $client->id);

    $batch = PayrollBatch::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'period_key' => '2026-05',
        'build_date' => '2026-06-03',
        'pay_date' => '2026-06-06',
        'record_count' => 1,
        'total_gross' => $record->gross,
        'built_by' => $admin->id,
        'built_at' => now(),
        'status' => 'synced',
        'approval_status' => 'exported',
    ]);

    $record->batch_id = $batch->id;
    $record->saveQuietly();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.batch.export', ['batch' => $batch->id, 'format' => 'csv']))
        ->assertOk()
        ->assertHeader('content-disposition');
});

test('payroll sync apply batch hours rejects caregivers missing from aw payroll', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'aw_employee_id' => '99',
    ]);
    $record = payrollReadyTestRecord($org->id, $employee->id, $client->id);

    $mapping = app(AccountantsWorldPayrollSyncService::class)->applyBatchHours([
        'keyData' => ['payrollId' => 1],
        'timeData' => [[
            'empId' => 42,
            'payTypes' => [['payTypeCode' => 'REG', 'hours' => 0, 'amount' => 0]],
        ]],
    ], collect([$record->load('employee')]));

    expect($mapping['errors'])->not->toBeEmpty()
        ->and($mapping['errors'][0])->toContain('Jane Doe');
});

test('exported batch cannot be re-synced without force flag', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $batch = PayrollBatch::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'period_key' => '2026-05',
        'build_date' => '2026-06-03',
        'pay_date' => '2026-06-06',
        'record_count' => 0,
        'total_gross' => 0,
        'built_by' => $admin->id,
        'built_at' => now(),
        'status' => 'synced',
        'approval_status' => 'exported',
        'aw_payroll_id' => 999,
        'aw_synced_at' => now(),
    ]);

    Http::fake();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('payroll.batch.export', $batch->id))
        ->assertRedirect(route('payroll.batch-queue'))
        ->assertSessionHas('warning');

    Http::assertNothingSent();
});

test('failed payroll sync stores aw sync error on batch', function () {
    config([
        'payroll.accountants_world_api_url' => 'https://dev-api.payrollrelief.com/integration',
        'payroll.accountants_world_app_id' => 'test-app-id',
        'payroll.accountants_world_pay_schedule_id' => 12,
    ]);

    Http::fake([
        'https://dev-api.payrollrelief.com/integration/payroll/GetNextPayrollData/12' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['aw_employee_id' => '42']);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);
    $record = payrollReadyTestRecord($org->id, $employee->id, $client->id);

    $batch = PayrollBatch::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'period_key' => '2026-05',
        'build_date' => '2026-06-03',
        'pay_date' => '2026-06-06',
        'record_count' => 1,
        'total_gross' => $record->gross,
        'built_by' => $super->id,
        'built_at' => now(),
        'status' => 'built',
        'approval_status' => 'approved',
    ]);

    $record->batch_id = $batch->id;
    $record->saveQuietly();
    $batch->load('payRecords.employee');

    $result = app(AccountantsWorldPayrollSyncService::class)->syncBatch($batch, $super);

    expect($result['success'])->toBeFalse()
        ->and($batch->fresh()->aw_sync_error)->not->toBeNull()
        ->and($batch->fresh()->approval_status)->toBe('approved');
});
