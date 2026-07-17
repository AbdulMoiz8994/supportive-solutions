<?php

use App\Models\BillingClaimAudit;
use App\Services\BillingClaimAuditWorkflowService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->workflow = app(BillingClaimAuditWorkflowService::class);
});

function workflowClaimDefaults(int $orgId, int $clientId, array $attributes = []): array
{
    return array_merge([
        'organization_id' => $orgId,
        'client_id' => $clientId,
        'claim_number' => '837P-WF-'.uniqid(),
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => '2024-05-01',
        'period_start' => '2024-05-01',
        'period_end' => '2024-05-31',
        'total_hours' => 50,
        'hourly_rate' => 30,
        'total_amount' => 1500,
        'submission_channel' => '837P - Availity',
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
        'audit_status' => BillingClaimAudit::AUDIT_NOT_REVIEWED,
        'lifecycle_events' => [],
        'documents' => [],
    ], $attributes);
}

test('T019 units calculate approved hours as units divided by four', function () {
    expect($this->workflow->calculateApprovedHoursFromT019Units(56))->toBe(14.0)
        ->and($this->workflow->calculateApprovedHoursFromT019Units(432))->toBe(108.0);
});

test('generic units calculate approved hours from unit minutes', function () {
    expect($this->workflow->calculateApprovedHoursFromUnits(56, 15))->toBe(14.0)
        ->and($this->workflow->calculateApprovedHoursFromUnits(8, 60))->toBe(8.0);
});

test('monthly hours calculate daily average for service month', function () {
    $month = Carbon::parse('2024-05-01');
    expect($this->workflow->calculateDailyAverageHours(310, $month))->toBe(round(310 / 31, 2));
});

test('authorization status resolves expired missing and expiring soon', function () {
    expect($this->workflow->resolveAuthorizationStatus(null, null))->toBe(BillingClaimAudit::AUTH_STATUS_MISSING)
        ->and($this->workflow->resolveAuthorizationStatus(now()->subMonths(2), now()->subDay()))->toBe(BillingClaimAudit::AUTH_STATUS_EXPIRED)
        ->and($this->workflow->resolveAuthorizationStatus(now()->subMonth(), now()->addDays(10)))->toBe(BillingClaimAudit::AUTH_STATUS_EXPIRING_SOON)
        ->and($this->workflow->resolveAuthorizationStatus(now()->subMonth(), now()->addMonths(3)))->toBe(BillingClaimAudit::AUTH_STATUS_ACTIVE);
});

test('balance is calculated server side from billed paid and adjustments', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $claim = BillingClaimAudit::withoutGlobalScopes()->create(workflowClaimDefaults($org->id, $client->id, [
        'claim_number' => '837P-BAL-'.uniqid(),
        'total_hours' => 100,
        'total_amount' => 3000,
        'expected_amount' => 3000,
        'paid_amount' => 1500,
        'adjustment_amount' => 200,
        'claim_status' => BillingClaimAudit::STATUS_AWAITING_PAYMENT,
        'billing_status' => BillingClaimAudit::BILLING_PENDING_PAYMENT,
    ]));

    $this->workflow->recalculateFinancials($claim);
    expect((float) $claim->balance_amount)->toBe(1300.0)
        ->and($claim->payment_status)->toBe(BillingClaimAudit::PAYMENT_PARTIAL);
});

test('paid in full is recognized when paid amount meets billed amount', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $claim = BillingClaimAudit::withoutGlobalScopes()->create(workflowClaimDefaults($org->id, $client->id, [
        'claim_number' => '837P-FULL-'.uniqid(),
        'expected_amount' => 1500,
        'claim_status' => BillingClaimAudit::STATUS_AWAITING_PAYMENT,
        'billing_status' => BillingClaimAudit::BILLING_PENDING_PAYMENT,
    ]));

    $this->workflow->applyEobPayment($claim, ['paid_amount' => 1500, 'payment_date' => '2024-06-15'], 1);
    expect($claim->payment_status)->toBe(BillingClaimAudit::PAYMENT_PAID_FULL)
        ->and($claim->billing_status)->toBe(BillingClaimAudit::BILLING_PAID);
});

test('missing authorization blocks billing readiness', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $claim = BillingClaimAudit::withoutGlobalScopes()->create(workflowClaimDefaults($org->id, $client->id, [
        'claim_number' => '837P-BLOCK-'.uniqid(),
        'total_hours' => 100,
        'total_amount' => 3000,
        'authorization_status' => BillingClaimAudit::AUTH_STATUS_MISSING,
        'visit_verification_status' => BillingClaimAudit::VISIT_VERIFIED,
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
    ]));

    $this->workflow->evaluateBillingReadiness($claim);
    expect($claim->billing_status)->toBe(BillingClaimAudit::BILLING_BLOCKED)
        ->and($claim->issue_flags)->toContain('missing_authorization');
});

test('manual override requires permission in http layer', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $staff = $this->createUser(\App\Models\User::ROLE_STAFF, ['organization_id' => $org->id]);
    $claim = BillingClaimAudit::withoutGlobalScopes()->create(workflowClaimDefaults($org->id, $client->id, [
        'claim_number' => '837P-OV-'.uniqid(),
        'billing_status' => BillingClaimAudit::BILLING_BLOCKED,
        'authorization_status' => BillingClaimAudit::AUTH_STATUS_EXPIRED,
        'issue_flags' => ['expired_authorization'],
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
    ]));

    $this->actingAsWithTwoFactor($staff)
        ->post(route('billing-claims-audit.override', $claim), ['override_reason' => 'Supervisor approved exception for retro auth.'])
        ->assertForbidden();
});

test('admin with override permission can override billing block with reason', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(\App\Models\User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = BillingClaimAudit::withoutGlobalScopes()->create(workflowClaimDefaults($org->id, $client->id, [
        'claim_number' => '837P-OV2-'.uniqid(),
        'billing_status' => BillingClaimAudit::BILLING_BLOCKED,
        'authorization_status' => BillingClaimAudit::AUTH_STATUS_EXPIRED,
        'issue_flags' => ['expired_authorization'],
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
    ]));

    $this->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.override', $claim), ['override_reason' => 'Supervisor approved exception for retro authorization paperwork.'])
        ->assertRedirect(route('billing-claims-audit.show', $claim));

    $claim->refresh();
    expect($claim->billing_status)->toBe(BillingClaimAudit::BILLING_READY)
        ->and($claim->override_reason)->not->toBeEmpty()
        ->and($claim->overridden_by)->toBe($admin->id);
});

test('filter by billing status works', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(\App\Models\User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $clientReady = $this->createClient($org->id, ['first_name' => 'Ready', 'last_name' => 'FilterClient']);
    $clientBlocked = $this->createClient($org->id, ['first_name' => 'Blocked', 'last_name' => 'FilterClient']);
    BillingClaimAudit::withoutGlobalScopes()->create(workflowClaimDefaults($org->id, $clientReady->id, [
        'total_amount' => 5555,
        'billing_status' => BillingClaimAudit::BILLING_READY,
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
    ]));
    BillingClaimAudit::withoutGlobalScopes()->create(workflowClaimDefaults($org->id, $clientBlocked->id, [
        'total_amount' => 6666,
        'billing_status' => BillingClaimAudit::BILLING_BLOCKED,
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
    ]));

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', ['period' => '2024-05', 'billing_status' => 'ready_to_bill']))
        ->assertOk()
        ->assertSee('Ready FilterClient')
        ->assertSee('$5,555.00')
        ->assertDontSee('Blocked FilterClient')
        ->assertDontSee('$6,666.00');
});

test('EOB payment data can be saved via form', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(\App\Models\User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = BillingClaimAudit::withoutGlobalScopes()->create(workflowClaimDefaults($org->id, $client->id, [
        'claim_number' => '837P-EOB-'.uniqid(),
        'total_hours' => 100,
        'total_amount' => 3000,
        'expected_amount' => 3000,
        'billing_status' => BillingClaimAudit::BILLING_SUBMITTED,
        'submitted_at' => now()->subDays(5),
    ]));

    $this->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.record-eob', $claim), [
            'paid_amount' => 1200,
            'payment_date' => '2024-06-10',
            'denial_reason' => 'Partial unit mismatch',
        ])
        ->assertRedirect(route('billing-claims-audit.show', $claim));

    $claim->refresh();
    expect((float) $claim->paid_amount)->toBe(1200.0)
        ->and($claim->payment_status)->toBe(BillingClaimAudit::PAYMENT_UNDERPAID)
        ->and($claim->rejection_reason)->toBe('Partial unit mismatch');
});

test('detail page shows billing audit workflow sections', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(\App\Models\User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = BillingClaimAudit::withoutGlobalScopes()->create(workflowClaimDefaults($org->id, $client->id, [
        'claim_number' => '837P-WF-'.uniqid(),
        'units' => 200,
        'service_code' => 'T019',
        'calculated_approved_hours' => 50,
        'authorization_status' => BillingClaimAudit::AUTH_STATUS_ACTIVE,
        'evv_status' => BillingClaimAudit::EVV_NOT_CONNECTED,
        'ai_extraction_status' => BillingClaimAudit::AI_NOT_CONNECTED,
        'billing_status' => BillingClaimAudit::BILLING_SUBMITTED,
    ]));

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.show', $claim))
        ->assertOk()
        ->assertSee('Billing audit workflow')
        ->assertSee('Authorization / care details')
        ->assertSee('Visit verification (EVV)')
        ->assertSee('AI Extraction Not Connected');
});

test('guest cannot download EOB files', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $claim = BillingClaimAudit::withoutGlobalScopes()->create(workflowClaimDefaults($org->id, $client->id, [
        'eob_document_path' => 'billing-claims-audit/eob/'.$org->id.'/sample.pdf',
    ]));

    $this->get(route('billing-claims-audit.eob.download', $claim))
        ->assertRedirect(route('signin'));
});

test('admin cannot download EOB from another organization', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $client = $this->createClient($orgA->id);
    $adminB = $this->createUser(\App\Models\User::ROLE_ADMIN, ['organization_id' => $orgB->id]);
    $claim = BillingClaimAudit::withoutGlobalScopes()->create(workflowClaimDefaults($orgA->id, $client->id, [
        'eob_document_path' => 'billing-claims-audit/eob/'.$orgA->id.'/sample.pdf',
    ]));

    $this->actingAsWithTwoFactor($adminB)
        ->get(route('billing-claims-audit.eob.download', $claim))
        ->assertNotFound();
});

test('EOB download rejects path outside organization storage prefix', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(\App\Models\User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = BillingClaimAudit::withoutGlobalScopes()->create(workflowClaimDefaults($org->id, $client->id, [
        'eob_document_path' => '../../../.env',
    ]));

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.eob.download', $claim))
        ->assertNotFound();
});

test('legacy T1019 service code still uses units divided by four calculation', function () {
    expect($this->workflow->calculateApprovedHoursFromT019Units(56))->toBe(14.0);
});
