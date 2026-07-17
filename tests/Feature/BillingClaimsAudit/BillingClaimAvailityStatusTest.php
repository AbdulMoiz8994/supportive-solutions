<?php

use App\Models\BillingClaimAudit;
use App\Models\User;
use App\Services\Availity\AvailityClaimStatusMapper;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Cache::flush();
    availityTestConfig();
});

test('availity client inquire claim status parses 200 response with claimStatuses', function () {
    availityHttpFake([
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'pending', 'id' => 'CS-100']],
        ], 200),
    ]);

    $result = app(\App\Services\Availity\AvailityClient::class)->inquireClaimStatus([
        'payer.id' => 'BCBSF',
        'claimNumber' => 'TEST-123',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('pending')
        ->and($result['reference_id'])->toBe('CS-100');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.availity.com/availity/v1/claim-statuses'
        && $request->hasHeader('X-HTTP-Method-Override', 'GET')
        && $request->hasHeader('Authorization', 'Bearer test-token'));
});

test('billing claim availity status sync updates audit record', function () {
    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id, ['member_id' => '4821234567']);

    availityHttpFake([
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'pending']],
        ], 200),
    ]);

    $audit = BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'claim_number' => '837P-STATUS-1',
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => '2024-05-01',
        'period_start' => '2024-05-01',
        'period_end' => '2024-05-31',
        'total_hours' => 108,
        'hourly_rate' => 30,
        'total_amount' => 3240,
        'submission_channel' => '837P - Availity',
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
        'billing_status' => BillingClaimAudit::BILLING_SUBMITTED,
        'submitted_at' => now(),
    ]);

    $result = app(\App\Services\Billing\BillingClaimAvailityStatusService::class)->sync($audit);

    expect($result['success'])->toBeTrue();

    $audit->refresh();
    expect($audit->availity_status)->toBe('pending')
        ->and($audit->availity_status_checked_at)->not->toBeNull()
        ->and($audit->billing_status)->toBe(BillingClaimAudit::BILLING_PENDING_PAYMENT);
});

test('authorized user can refresh availity status from claim detail', function () {
    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id, ['member_id' => '4821234567']);
    $admin = test()->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    availityHttpFake([
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'submitted']],
        ], 200),
    ]);

    $claim = BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'claim_number' => '837P-UI-1',
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => '2024-05-01',
        'period_start' => '2024-05-01',
        'period_end' => '2024-05-31',
        'total_hours' => 10,
        'hourly_rate' => 30,
        'total_amount' => 300,
        'submission_channel' => '837P - Availity',
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
        'submitted_at' => now(),
    ]);

    test()->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.refresh-availity-status.claim', $claim))
        ->assertRedirect(route('billing-claims-audit.show', $claim))
        ->assertSessionHas('success');

    expect($claim->fresh()->availity_status)->toBe('submitted');
});

test('claim status mapper builds form fields from billing audit', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id, ['member_id' => '4821234567', 'first_name' => 'Jane', 'last_name' => 'Doe']);
    $audit = BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'claim_number' => 'BCA-99',
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => '2024-05-01',
        'period_start' => '2024-05-01',
        'period_end' => '2024-05-31',
        'total_hours' => 40,
        'hourly_rate' => 30,
        'total_amount' => 1200,
        'submission_channel' => '837P - Availity',
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
    ]);

    $fields = app(AvailityClaimStatusMapper::class)->fromBillingClaimAudit($audit);

    expect($fields['claimNumber'])->toBe('BCA-99')
        ->and($fields['subscriber.memberId'])->toBe('4821234567')
        ->and($fields['patient.lastName'])->toBe('Doe');
});

test('generate submit auto refreshes availity status for routed claims', function () {
    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id, ['member_id' => '4821234567']);
    $admin = test()->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    availityHttpFake([
        'https://api.availity.com/availity/v1/professional-claims' => Http::response([], 202, [
            'Location' => 'https://api.availity.com/availity/v1/professional-claims/REF-GEN-1',
        ]),
        'https://api.availity.com/availity/v1/professional-claims/*' => Http::response([
            'status' => 'pending',
            'id' => 'REF-GEN-1',
        ], 200),
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'pending', 'id' => 'CS-GEN-1']],
        ], 200),
    ]);

    $claim = BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'claim_number' => '837P-GEN-SUBMIT-1',
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => '2024-05-01',
        'period_start' => '2024-05-01',
        'period_end' => '2024-05-31',
        'total_hours' => 108,
        'hourly_rate' => 30,
        'total_amount' => 3240,
        'submission_channel' => '837P - Availity',
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
        'billing_status' => BillingClaimAudit::BILLING_SUBMITTED,
        'submitted_at' => null,
    ]);

    test()->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.generate-submit'), ['period' => '2024-05'])
        ->assertRedirect(route('billing-claims-audit.index', ['period' => '2024-05']))
        ->assertSessionHas('success');

    $claim->refresh();

    expect($claim->submitted_at)->not->toBeNull()
        ->and($claim->availity_reference_id)->toBe('REF-GEN-1')
        ->and($claim->availity_status)->not->toBeNull();
});

test('authorized user can batch refresh availity status for submitted period claims', function () {
    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id, ['member_id' => '4821234567']);
    $admin = test()->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    availityHttpFake([
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'processing']],
        ], 200),
    ]);

    $claim = billingClaimAuditRecord($org->id, $client->id, [
        'claim_number' => '837P-BATCH-1',
    ]);

    test()->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.refresh-availity-status'), ['period' => '2024-05'])
        ->assertRedirect(route('billing-claims-audit.index', ['period' => '2024-05']))
        ->assertSessionHas('success');

    expect($claim->fresh()->availity_status)->toBe('processing');
});

test('refresh availity status returns warning for non availity claim', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $admin = test()->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $claim = billingClaimAuditRecord($org->id, $client->id, [
        'program_type' => BillingClaimAudit::PROGRAM_DHS,
        'submission_channel' => 'Paper',
    ]);

    test()->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.refresh-availity-status.claim', $claim))
        ->assertRedirect(route('billing-claims-audit.show', $claim))
        ->assertSessionHas('warning');
});

test('refresh availity status returns warning when Availity inquiry fails', function () {
    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id, ['member_id' => '4821234567']);
    $admin = test()->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    availityHttpFake([
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'userMessage' => 'Payer not found',
        ], 400),
    ]);

    $claim = billingClaimAuditRecord($org->id, $client->id, ['claim_number' => '837P-FAIL-1']);

    test()->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.refresh-availity-status.claim', $claim))
        ->assertRedirect(route('billing-claims-audit.show', $claim))
        ->assertSessionHas('warning');

    expect($claim->fresh()->availity_status)->toBe('failed');
});

test('employee without edit permission cannot refresh availity status', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $employee = test()->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $claim = billingClaimAuditRecord($org->id, $client->id);

    test()->actingAsWithTwoFactor($employee)
        ->post(route('billing-claims-audit.refresh-availity-status.claim', $claim))
        ->assertForbidden();
});

test('generate submit is blocked when CP-01 hold claims remain in period', function () {
    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id);
    $admin = test()->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    Http::fake();

    billingClaimAuditRecord($org->id, $client->id, [
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
        'hold_reason' => 'CP-01 prior balance',
        'submitted_at' => null,
    ]);

    test()->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.generate-submit'), ['period' => '2024-05'])
        ->assertRedirect(route('billing-claims-audit.index', ['period' => '2024-05']))
        ->assertSessionHas('warning');

    Http::assertNothingSent();
});

test('generate submit does not call Availity for DHS claims and emails ASW instead', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $admin = test()->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    mockGoogleWorkspaceForBilling();

    Http::fake([
        'https://www.michigan.gov/*' => Http::response('', 200),
    ]);

    $claim = billingClaimAuditRecord($org->id, $client->id, [
        'program_type' => BillingClaimAudit::PROGRAM_DHS,
        'submission_channel' => 'Home Help - Sigma Portal',
        'billing_route' => 'sigma_portal',
        'submitted_at' => null,
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
        'billing_status' => BillingClaimAudit::BILLING_READY,
    ]);

    test()->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.generate-submit'), ['period' => '2024-05'])
        ->assertSessionHas('success');

    $claim->refresh();

    expect($claim->submitted_at)->not->toBeNull()
        ->and($claim->availity_status)->toBeNull()
        ->and($claim->billing_status)->toBe(BillingClaimAudit::BILLING_SENT);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.availity.com/availity/v1/professional-claims'));
});

test('claim detail page shows availity status panel for routed claims', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $admin = test()->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $claim = billingClaimAuditRecord($org->id, $client->id, [
        'availity_status' => 'pending',
        'availity_reference_id' => 'REF-UI-1',
        'availity_status_checked_at' => now(),
    ]);

    test()->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.show', $claim))
        ->assertOk()
        ->assertSee('Availity claim status')
        ->assertSee('Check Availity status')
        ->assertSee('REF-UI-1');
});
