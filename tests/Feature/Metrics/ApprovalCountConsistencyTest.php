<?php

use App\Models\Billing;
use App\Models\BillingClaimAudit;
use App\Models\User;
use App\Services\WorkflowQueueService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    seedModuleBasics();
    config(['workflow_queues.demo_fallback' => false]);
});

function pendingBillingFor(int $orgId, int $clientId, string $invoice): Billing
{
    return Billing::withoutGlobalScopes()->create([
        'organization_id' => $orgId,
        'client_id' => $clientId,
        'invoice_number' => $invoice,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'total_amount' => 150,
        'status' => 'Pending',
    ]);
}

test('dashboard, workflow queue, sidebar and staff read the same approval count', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $clientA = $this->createClient($org->id, ['first_name' => 'Count', 'last_name' => 'One']);
    $clientB = $this->createClient($org->id, ['first_name' => 'Count', 'last_name' => 'Two']);

    pendingBillingFor($org->id, $clientA->id, 'INV-CONS-001');
    pendingBillingFor($org->id, $clientA->id, 'INV-CONS-002');

    BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $clientB->id,
        'claim_number' => 'CLM-CONS-001',
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => now()->startOfMonth()->toDateString(),
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'hourly_rate' => 20,
        'total_amount' => 100,
        'submission_channel' => 'Availity',
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
        'billing_status' => BillingClaimAudit::BILLING_BLOCKED,
    ]);

    $this->actingAsWithTwoFactor($admin);

    $service = app(WorkflowQueueService::class);
    $today = Carbon::now()->startOfDay();

    $payload = $service->approvalPayload($org->id, $today);
    $snapshot = $service->queueSnapshot($org->id, $today);

    expect($payload['approvalCount'])->toBe(3)
        ->and($snapshot['approvalCount'])->toBe(3)
        ->and($service->approvalCount($org->id))->toBe(3);

    // Blocked claims surface as their own chip, matching the Billing page's
    // on-hold notion instead of being lumped into "billing holds".
    $chipLabels = collect($payload['approvalChips'])->pluck('label');
    expect($chipLabels)->toContain('2 billing holds')
        ->and($chipLabels)->toContain('1 held claim');
});

test('staff awaiting count matches dashboard when demo fallback cards exist', function () {
    config(['workflow_queues.demo_fallback' => true]);

    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin);

    $service = app(WorkflowQueueService::class);
    $count = $service->approvalCount($org->id);
    $payload = $service->approvalPayload($org->id, Carbon::now()->startOfDay());

    expect($count)->toBe($payload['approvalCount'])
        ->and($count)->toBe(0);

    $this->get(route('staff.index'))
        ->assertOk()
        ->assertSee('Awaiting your approval', false);
});

test('resolving an item on the workflow queue page also drops it from the dashboard count', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Resolve', 'last_name' => 'Sync']);

    $billing = pendingBillingFor($org->id, $client->id, 'INV-SYNC-001');
    pendingBillingFor($org->id, $client->id, 'INV-SYNC-002');

    $this->actingAsWithTwoFactor($admin);

    $service = app(WorkflowQueueService::class);
    $today = Carbon::now()->startOfDay();

    expect($service->approvalPayload($org->id, $today)['approvalCount'])->toBe(2);

    // Resolve one item through the Workflow Queue page (demo-mode resolution
    // records a WorkflowQueueItem instead of mutating the billing).
    $service->applyAction($org->id, 'billing-'.$billing->id, 'reject');

    expect($service->approvalPayload($org->id, $today)['approvalCount'])->toBe(1)
        ->and($service->approvalCount($org->id))->toBe(1)
        ->and($service->queueSnapshot($org->id, $today)['approvalCount'])->toBe(1);
});

test('operations staff cannot approve queue items or reveal ssn', function () {
    $org = $this->createOrganization();
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['ssn_encrypted' => '123456789']);
    $billing = pendingBillingFor($org->id, $client->id, 'INV-PERM-001');

    $this->actingAsWithTwoFactor($staff)
        ->post(route('dashboard.approve', ['type' => 'billing', 'id' => $billing->id]))
        ->assertForbidden();

    $this->actingAsWithTwoFactor($staff)
        ->get(route('clients.ssn.reveal', $client->id))
        ->assertForbidden();
});

test('admin can still approve queue items and reveal ssn', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['ssn_encrypted' => '123456789']);
    $billing = pendingBillingFor($org->id, $client->id, 'INV-PERM-002');

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('dashboard.approve', ['type' => 'billing', 'id' => $billing->id]))
        ->assertOk();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.ssn.reveal', $client->id))
        ->assertOk()
        ->assertJsonPath('ssn', '123-45-6789');
});
