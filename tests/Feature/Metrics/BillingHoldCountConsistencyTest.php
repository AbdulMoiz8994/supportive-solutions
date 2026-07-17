<?php

use App\Models\Billing;
use App\Models\BillingClaimAudit;
use App\Models\User;
use App\Services\ApprovalQueueMetricsService;
use App\Services\BillingClaimsAuditService;
use App\Services\WorkflowQueueService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    seedModuleBasics();
    config(['workflow_queues.demo_fallback' => false]);
});

test('billing page on hold tab count matches shared metrics service', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Hold', 'last_name' => 'Client']);
    $period = now()->startOfMonth();

    BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'claim_number' => 'CLM-HOLD-001',
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => $period->toDateString(),
        'period_start' => $period->toDateString(),
        'period_end' => $period->copy()->endOfMonth()->toDateString(),
        'hourly_rate' => 20,
        'total_amount' => 100,
        'submission_channel' => 'Availity',
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
        'billing_status' => BillingClaimAudit::BILLING_BLOCKED,
    ]);

    BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'claim_number' => 'CLM-PAID-001',
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => $period->toDateString(),
        'period_start' => $period->toDateString(),
        'period_end' => $period->copy()->endOfMonth()->toDateString(),
        'hourly_rate' => 20,
        'total_amount' => 200,
        'submission_channel' => 'Availity',
        'claim_status' => BillingClaimAudit::STATUS_PAID,
        'billing_status' => BillingClaimAudit::BILLING_PAID,
    ]);

    $metrics = app(ApprovalQueueMetricsService::class);
    $auditService = app(BillingClaimsAuditService::class);

    $tabCounts = $auditService->tabCounts($org->id, ['period' => $period->format('Y-m')]);
    $summary = $auditService->summaryForPeriod($org->id, $period);

    expect($metrics->onHoldClaimCount($org->id, $period))->toBe(1)
        ->and($tabCounts['on_hold'])->toBe(1)
        ->and($summary['on_hold_count'])->toBe(1);
});

test('dashboard held claim chip count matches billing page for current cycle', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Chip', 'last_name' => 'Sync']);
    $period = now()->startOfMonth();

    BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'claim_number' => 'CLM-CHIP-001',
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => $period->toDateString(),
        'period_start' => $period->toDateString(),
        'period_end' => $period->copy()->endOfMonth()->toDateString(),
        'hourly_rate' => 20,
        'total_amount' => 100,
        'submission_channel' => 'Availity',
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
        'billing_status' => BillingClaimAudit::BILLING_BLOCKED,
    ]);

    $this->actingAsWithTwoFactor($admin);

    $metrics = app(ApprovalQueueMetricsService::class);
    $payload = app(WorkflowQueueService::class)->approvalPayload($org->id, Carbon::now()->startOfDay());
    $chipLabels = collect($payload['approvalChips'])->pluck('label');

    expect($metrics->onHoldClaimCount($org->id, $period))->toBe(1)
        ->and($chipLabels)->toContain('1 held claim');

    $this->get(route('billing-claims-audit.index', ['period' => $period->format('Y-m')]))
        ->assertOk()
        ->assertSee('— 1 on hold', false);
});

test('dashboard billing hold chip uses current cycle count from shared metrics', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Old', 'last_name' => 'Bill']);

    $lastMonth = now()->subMonth();

    Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-OLD-001',
        'period_start' => $lastMonth->copy()->startOfMonth()->toDateString(),
        'period_end' => $lastMonth->copy()->endOfMonth()->toDateString(),
        'total_amount' => 150,
        'status' => 'Pending',
    ]);

    $this->actingAsWithTwoFactor($admin);

    $metrics = app(ApprovalQueueMetricsService::class);
    $payload = app(WorkflowQueueService::class)->approvalPayload($org->id, Carbon::now()->startOfDay());
    $chipLabels = collect($payload['approvalChips'])->pluck('label');

    expect($metrics->pendingBillingHoldCount($org->id, now()->startOfMonth()))->toBe(0)
        ->and($chipLabels)->not->toContain('1 billing hold')
        ->and($payload['approvalCount'])->toBe(1);
});

test('workflow queue snapshot count matches dashboard when demo fallback cards exist', function () {
    config(['workflow_queues.demo_fallback' => true]);

    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin);

    $service = app(WorkflowQueueService::class);
    $today = Carbon::now()->startOfDay();

    $payload = $service->approvalPayload($org->id, $today);
    $snapshot = $service->queueSnapshot($org->id, $today);

    expect($snapshot['approvalCount'])->toBe(0)
        ->and($snapshot['approvalCount'])->toBe($payload['approvalCount'])
        ->and($service->approvalCount($org->id))->toBe(0);

    $this->get(route('workflow-queues'))
        ->assertOk()
        ->assertSee('0 awaiting your approval', false);
});

test('staff awaiting kpi numeric value matches dashboard approval count', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Staff', 'last_name' => 'Sync']);

    Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-STAFF-001',
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'total_amount' => 150,
        'status' => 'Pending',
    ]);

    $this->actingAsWithTwoFactor($admin);

    $expected = app(WorkflowQueueService::class)->approvalCount($org->id);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee((string) $expected, false);

    $this->get(route('staff.index'))
        ->assertOk()
        ->assertSee((string) $expected, false);
});
