<?php

use App\Models\ComplianceForm;
use App\Models\PayRecord;
use App\Models\PayrollBatch;
use App\Models\User;
use App\Services\PayrollBatchService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('build batch excludes in-grace records even when ids forced in request', function () {
    $this->travelTo('2026-06-15');

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $readyEmp = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    $graceEmp = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);

    $ready = payrollReadyTestRecord($org->id, $readyEmp->id, $client->id, [], [
        'submitted_at' => '2026-05-20 10:00:00',
    ]);
    $inGrace = payrollReadyTestRecord($org->id, $graceEmp->id, $client->id, [
        'status' => PayRecord::STATUS_IN_GRACE,
    ], [
        'submitted_at' => '2026-06-10 10:00:00',
    ]);

    $this->actingAsWithTwoFactor($super)
        ->post(route('payroll.build-batch'), [
            'period'     => '2026-05',
            'record_ids' => [$ready->id, $inGrace->id],
        ])
        ->assertRedirect();

    expect(PayrollBatch::withoutGlobalScopes()->count())->toBe(1);
    expect($ready->fresh()->batch_id)->not->toBeNull();
    expect($inGrace->fresh()->batch_id)->toBeNull();
});

test('batch build is idempotent', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    payrollReadyTestRecord($org->id, $employee->id, $client->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($super)
        ->post(route('payroll.build-batch'), ['period' => '2026-05']);

    $this->actingAsWithTwoFactor($super)
        ->post(route('payroll.build-batch'), ['period' => '2026-05']);

    expect(PayrollBatch::withoutGlobalScopes()->where('period_key', '2026-05')->count())->toBe(1);
});

test('batch service includes only ready records', function () {
    $this->travelTo('2026-06-15');

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $readyEmp = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    $graceEmp = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    $heldEmp = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);

    payrollReadyTestRecord($org->id, $readyEmp->id, $client->id, [], [
        'submitted_at' => '2026-05-20 10:00:00',
    ]);
    payrollReadyTestRecord($org->id, $graceEmp->id, $client->id, [], [
        'submitted_at' => '2026-06-10 10:00:00',
    ]);
    payrollReadyTestRecord($org->id, $heldEmp->id, $client->id, [
        'status'      => PayRecord::STATUS_HELD,
        'hold_reason' => 'Hold',
    ]);

    $batch = app(PayrollBatchService::class)->buildBatch(
        $org->id,
        \Carbon\Carbon::createFromFormat('Y-m', '2026-05')->startOfMonth(),
        $super
    );

    expect($batch->record_count)->toBe(1);
});

test('pay record status is not mass assignable to ready via direct update in workflow', function () {
    $this->travelTo('2026-06-03');

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    $form = ComplianceForm::withoutGlobalScopes()->create([
        'organization_id'  => $org->id,
        'employee_id'      => $employee->id,
        'client_id'        => $client->id,
        'period'           => '2026-05',
        'status'           => ComplianceForm::STATUS_VERIFIED,
        'delivered_hours'  => 108,
        'submitted_at'     => '2026-06-02 10:00:00',
    ]);

    $record = payrollTestRecord($org->id, $employee->id, $client->id, [
        'status'             => PayRecord::STATUS_IN_GRACE,
        'compliance_form_id' => $form->id,
        'grace_end_date'     => '2026-06-12',
    ]);

    app(\App\Services\PayrollRecordWorkflowService::class)->refreshRecord($record);

    expect($record->status)->toBe(PayRecord::STATUS_IN_GRACE);
});

test('billing claim audit rate unchanged after payroll wage update', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['billing_rate' => 30.00]);
    $employee = $this->createEmployee($org->id);
    $record = payrollTestRecord($org->id, $employee->id, $client->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $claim = \App\Models\BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id'    => $org->id,
        'client_id'          => $client->id,
        'claim_number'       => 'TEST-001',
        'program_type'       => 'MICH',
        'billing_period'     => '2026-05-01',
        'period_start'       => '2026-05-01',
        'period_end'         => '2026-05-31',
        'total_hours'        => 108,
        'hourly_rate'        => 30.00,
        'total_amount'       => 3240.00,
        'claim_status'       => 'submitted',
        'submission_channel' => '837P - Availity',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->patch(route('payroll.update-wage', $record), ['hourly_wage' => 18.00]);

    expect((float) $claim->fresh()->hourly_rate)->toBe(30.00);
});
