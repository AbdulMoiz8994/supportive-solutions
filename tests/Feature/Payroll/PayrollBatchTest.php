<?php

use App\Models\PayRecord;
use App\Models\PayrollBatch;
use App\Models\User;
use App\Services\PayrollBatchService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

test('batch build excludes paid records even when forced by id', function () {
    $this->travelTo('2026-06-15');

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $readyEmp = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    $paidEmp = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    $ready = payrollReadyTestRecord($org->id, $readyEmp->id, $client->id);
    $paid = payrollTestRecord($org->id, $paidEmp->id, $client->id, ['status' => PayRecord::STATUS_PAID]);

    $this->actingAsWithTwoFactor($super)
        ->post(route('payroll.build-batch'), [
            'period'     => '2026-05',
            'record_ids' => [$ready->id, $paid->id],
        ])
        ->assertRedirect();

    expect($ready->fresh()->batch_id)->not->toBeNull();
    expect($paid->fresh()->batch_id)->toBeNull();
});

test('duplicate batch build returns same batch', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);
    $employee = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    payrollReadyTestRecord($org->id, $employee->id, $client->id);

    $this->actingAsWithTwoFactor($super)->post(route('payroll.build-batch'), ['period' => '2026-05']);
    $this->actingAsWithTwoFactor($super)->post(route('payroll.build-batch'), ['period' => '2026-05']);

    expect(PayrollBatch::withoutGlobalScopes()->where('period_key', '2026-05')->count())->toBe(1);
});

test('batch service creates audit log', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);
    $employee = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    payrollReadyTestRecord($org->id, $employee->id, $client->id);

    app(PayrollBatchService::class)->buildBatch(
        $org->id,
        \Carbon\Carbon::createFromFormat('Y-m', '2026-05')->startOfMonth(),
        $super
    );

    $this->assertDatabaseHas('payroll_audit_logs', ['action' => 'batch_build']);
});

test('hold release during active grace returns in grace status', function () {
    $this->travelTo('2026-06-03');

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    \App\Models\ComplianceForm::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'employee_id'     => $employee->id,
        'client_id'       => $client->id,
        'period'          => '2026-05',
        'status'          => 'Verified',
        'delivered_hours' => 108,
        'submitted_at'    => '2026-06-02 10:00:00',
    ]);

    $record = payrollTestRecord($org->id, $employee->id, $client->id, [
        'status'      => PayRecord::STATUS_HELD,
        'hold_reason' => 'DIG re-check',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('payroll.release-hold', $record))
        ->assertRedirect();

    expect($record->fresh()->status)->toBe(PayRecord::STATUS_IN_GRACE);
});
