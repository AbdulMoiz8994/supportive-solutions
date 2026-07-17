<?php

use App\Models\ComplianceForm;
use App\Models\PayRecord;
use App\Models\PayrollBatch;
use App\Models\Schedule;
use App\Models\User;
use App\Services\PayrollBatchService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

test('build batch refreshes hours from verified form and clean evv visits', function () {
    $this->travelTo('2026-06-15');

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id, ['hourly_wage' => 15.00]);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $form = payrollVerifiedComplianceForm($org->id, $caregiver->id, $client->id, [
        'delivered_hours' => 108,
        'submitted_at'    => '2026-05-20 10:00:00',
    ]);

    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'status'      => Schedule::STATUS_COMPLETED,
        'date'        => '2026-05-10',
        'total_hours' => 6,
        'evv_status'  => true,
    ]);

    $record = payrollTestRecord($org->id, $caregiver->id, $client->id, [
        'compliance_form_id' => $form->id,
        'hours'              => 999,
        'gross'              => 14985,
        'status'             => PayRecord::STATUS_READY,
    ]);

    $batch = app(PayrollBatchService::class)->buildBatch(
        $org->id,
        \Carbon\Carbon::createFromFormat('Y-m', '2026-05')->startOfMonth(),
        $super
    );

    $record->refresh();

    expect($batch->record_count)->toBe(1)
        ->and((float) $batch->total_gross)->toBe(90.0)
        ->and((float) $record->hours)->toBe(6.0)
        ->and((float) $record->gross)->toBe(90.0)
        ->and($record->batch_id)->toBe($batch->id);
});

test('build batch excludes submitted but unverified compliance forms', function () {
    $this->travelTo('2026-06-15');

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $form = ComplianceForm::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'employee_id'     => $caregiver->id,
        'client_id'       => $client->id,
        'period'          => '2026-05',
        'status'          => ComplianceForm::STATUS_SUBMITTED,
        'delivered_hours' => 108,
        'submitted_at'    => '2026-05-20 10:00:00',
    ]);

    $record = payrollTestRecord($org->id, $caregiver->id, $client->id, [
        'compliance_form_id' => $form->id,
        'status'             => PayRecord::STATUS_READY,
    ]);

    app(PayrollBatchService::class)->buildBatch(
        $org->id,
        \Carbon\Carbon::createFromFormat('Y-m', '2026-05')->startOfMonth(),
        $super
    );

    expect($record->fresh()->batch_id)->toBeNull()
        ->and($record->fresh()->status)->toBe(PayRecord::STATUS_PENDING);
});

test('build batch excludes non exempt caregivers without clean evv hours', function () {
    $this->travelTo('2026-06-15');

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => $org->id]);

    $form = payrollVerifiedComplianceForm($org->id, $caregiver->id, $client->id, [
        'delivered_hours' => 108,
        'submitted_at'    => '2026-05-20 10:00:00',
    ]);

    $record = payrollTestRecord($org->id, $caregiver->id, $client->id, [
        'compliance_form_id' => $form->id,
        'status'             => PayRecord::STATUS_READY,
    ]);

    app(PayrollBatchService::class)->buildBatch(
        $org->id,
        \Carbon\Carbon::createFromFormat('Y-m', '2026-05')->startOfMonth(),
        $super
    );

    expect($record->fresh()->batch_id)->toBeNull()
        ->and($record->fresh()->status)->toBe(PayRecord::STATUS_AWAITING_FORM);
});

test('submitted compliance form keeps pay record pending until wellness verification', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id, ['live_in' => true, 'evv_exempt' => true]);

    ComplianceForm::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'employee_id'     => $employee->id,
        'client_id'       => $client->id,
        'period'          => '2026-05',
        'status'          => ComplianceForm::STATUS_SUBMITTED,
        'delivered_hours' => 108,
        'submitted_at'    => now()->subDays(3),
    ]);

    $record = payrollTestRecord($org->id, $employee->id, $client->id);

    app(\App\Services\PayrollRecordWorkflowService::class)->refreshRecord($record);

    expect($record->status)->toBe(PayRecord::STATUS_PENDING);
});
