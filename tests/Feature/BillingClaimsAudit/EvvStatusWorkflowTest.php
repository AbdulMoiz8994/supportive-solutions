<?php

use App\Models\BillingClaimAudit;
use App\Models\Schedule;
use App\Services\BillingClaimAuditWorkflowService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->workflow = app(BillingClaimAuditWorkflowService::class);
});

test('completed visits with local EVV set verified_local when HHA not connected', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employee = $this->createEmployee($org->id);

    $this->createSchedule($org->id, $client->id, $employee->id, [
        'date' => '2024-05-10',
        'status' => Schedule::STATUS_COMPLETED,
        'evv_status' => true,
        'actual_clock_in' => '2024-05-10 08:00:00',
        'actual_clock_out' => '2024-05-10 12:00:00',
        'total_hours' => 4,
    ]);

    $audit = BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'employee_id' => $employee->id,
        'claim_number' => 'EVV-LOCAL-'.uniqid(),
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => '2024-05-01',
        'period_start' => '2024-05-01',
        'period_end' => '2024-05-31',
        'total_hours' => 4,
        'hourly_rate' => 30,
        'total_amount' => 120,
        'submission_channel' => '837P - Availity',
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
        'audit_status' => BillingClaimAudit::AUDIT_NOT_REVIEWED,
        'evv_exempt' => false,
        'lifecycle_events' => [],
        'documents' => [],
    ]);

    $this->workflow->syncVisitHours($audit);

    expect($audit->evv_status)->toBe(BillingClaimAudit::EVV_VERIFIED_LOCAL)
        ->and($audit->visit_verification_status)->toBe(BillingClaimAudit::VISIT_VERIFIED)
        ->and((float) $audit->completed_visit_hours)->toBe(4.0);
});

test('empty schedules remain not connected with missing visit verification', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);

    $audit = BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'claim_number' => 'EVV-EMPTY-'.uniqid(),
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => '2024-05-01',
        'period_start' => '2024-05-01',
        'period_end' => '2024-05-31',
        'total_hours' => 0,
        'hourly_rate' => 30,
        'total_amount' => 0,
        'submission_channel' => '837P - Availity',
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
        'audit_status' => BillingClaimAudit::AUDIT_NOT_REVIEWED,
        'evv_exempt' => false,
        'lifecycle_events' => [],
        'documents' => [],
    ]);

    $this->workflow->syncVisitHours($audit);

    expect($audit->evv_status)->toBe(BillingClaimAudit::EVV_NOT_CONNECTED)
        ->and($audit->visit_verification_status)->toBe(BillingClaimAudit::VISIT_MISSING);
});
