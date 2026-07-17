<?php

use App\Models\BillingClaimAudit;
use App\Services\Billing\BillingClaimAvailityStatusService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    availityTestConfig();
});

test('sync throws when claim is not routed through Availity', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $audit = billingClaimAuditRecord($org->id, $client->id, [
        'program_type' => BillingClaimAudit::PROGRAM_DHS,
        'submission_channel' => 'Paper',
    ]);

    app(BillingClaimAvailityStatusService::class)->sync($audit);
})->throws(InvalidArgumentException::class, 'This claim is not routed through Availity.');

test('sync uses professional claim endpoint when reference id exists', function () {
    availityHttpFake([
        'https://api.availity.com/availity/v1/professional-claims/*' => Http::response([
            'id' => 'REF-PRO-1',
            'status' => 'approved',
        ], 200),
    ]);

    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $audit = billingClaimAuditRecord($org->id, $client->id, [
        'availity_reference_id' => 'REF-PRO-1',
        'billing_status' => BillingClaimAudit::BILLING_SUBMITTED,
    ]);

    $result = app(BillingClaimAvailityStatusService::class)->sync($audit);

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('approved');

    $audit->refresh();
    expect($audit->availity_status)->toBe('approved')
        ->and($audit->billing_status)->toBe(BillingClaimAudit::BILLING_PAID)
        ->and($audit->payment_status)->toBe(BillingClaimAudit::PAYMENT_PAID_FULL)
        ->and($audit->claim_status)->toBe(BillingClaimAudit::STATUS_PAID);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/professional-claims/REF-PRO-1'));
    Http::assertNotSent(fn ($request) => $request->url() === 'https://api.availity.com/availity/v1/claim-statuses');
});

test('sync falls back to 276 inquiry when professional claim poll fails', function () {
    availityHttpFake([
        'https://api.availity.com/availity/v1/professional-claims/*' => Http::response(['message' => 'Not found'], 404),
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'submitted', 'id' => 'CS-FALLBACK']],
        ], 200),
    ]);

    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id, ['member_id' => '4821234567']);
    $audit = billingClaimAuditRecord($org->id, $client->id, [
        'availity_reference_id' => 'REF-MISSING',
    ]);

    $result = app(BillingClaimAvailityStatusService::class)->sync($audit);

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('submitted');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.availity.com/availity/v1/claim-statuses');
});

test('sync maps denied Availity status to billing denied and rejected claim status', function () {
    availityHttpFake([
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'denied']],
        ], 200),
    ]);

    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id, ['member_id' => '4821234567']);
    $audit = billingClaimAuditRecord($org->id, $client->id);

    app(BillingClaimAvailityStatusService::class)->sync($audit);

    $audit->refresh();
    expect($audit->billing_status)->toBe(BillingClaimAudit::BILLING_DENIED)
        ->and($audit->payment_status)->toBe(BillingClaimAudit::PAYMENT_DENIED)
        ->and($audit->claim_status)->toBe(BillingClaimAudit::STATUS_REJECTED);
});

test('sync extracts paid amount and calculates remaining balance', function () {
    availityHttpFake([
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'paid', 'paidAmount' => 2000.00]],
        ], 200),
    ]);

    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id, ['member_id' => '4821234567']);
    $audit = billingClaimAuditRecord($org->id, $client->id, ['total_amount' => 3240.00]);

    app(BillingClaimAvailityStatusService::class)->sync($audit);

    $audit->refresh();
    expect($audit->paid_amount)->toBe('2000.00')
        ->and($audit->balance_amount)->toBe('1240.00')
        ->and($audit->billing_status)->toBe(BillingClaimAudit::BILLING_PAID);
});

test('sync does not set updated_by when user id is omitted', function () {
    availityHttpFake([
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'pending']],
        ], 200),
    ]);

    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id, ['member_id' => '4821234567']);
    $audit = billingClaimAuditRecord($org->id, $client->id, ['updated_by' => null]);

    app(BillingClaimAvailityStatusService::class)->sync($audit);

    expect($audit->fresh()->updated_by)->toBeNull();
});

test('sync stores updated_by when user id is provided', function () {
    availityHttpFake([
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'pending']],
        ], 200),
    ]);

    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id, ['member_id' => '4821234567']);
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $admin = test()->createUser(\App\Models\User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $audit = billingClaimAuditRecord($org->id, $client->id);

    app(BillingClaimAvailityStatusService::class)->sync($audit, $admin->id);

    expect($audit->fresh()->updated_by)->toBe($admin->id);
});

test('syncPeriod counts synced failed and skipped claims for submitted period rows', function () {
    availityHttpFake([
        'https://api.availity.com/availity/v1/claim-statuses' => Http::sequence()
            ->push(['totalCount' => 1, 'claimStatuses' => [['status' => 'pending']]], 200)
            ->push(['userMessage' => 'Invalid inquiry'], 400),
    ]);

    $org = test()->createOrganization(['agency_npi' => '1619784667']);
    $client = test()->createClient($org->id, ['member_id' => '4821234567']);

    billingClaimAuditRecord($org->id, $client->id, [
        'claim_number' => 'SYNC-OK',
        'submitted_at' => now(),
    ]);
    billingClaimAuditRecord($org->id, $client->id, [
        'claim_number' => 'SYNC-FAIL',
        'submitted_at' => now(),
    ]);
    billingClaimAuditRecord($org->id, $client->id, [
        'claim_number' => 'SYNC-SKIP',
        'submission_channel' => 'Paper',
        'billing_route' => 'availity_837p',
        'submitted_at' => now(),
    ]);
    billingClaimAuditRecord($org->id, $client->id, [
        'claim_number' => 'SYNC-NOT-SUBMITTED',
        'submitted_at' => null,
    ]);

    $counts = app(BillingClaimAvailityStatusService::class)->syncPeriod(
        $org->id,
        \Carbon\Carbon::parse('2024-05-01')
    );

    expect($counts)->toBe([
        'synced' => 1,
        'failed' => 1,
        'skipped' => 1,
    ]);
});

test('syncPeriod scopes results to organization when organization id provided', function () {
    availityHttpFake([
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'pending']],
        ], 200),
    ]);

    $orgA = test()->createOrganization(['agency_npi' => '1619784667']);
    $orgB = test()->createOrganization(['agency_npi' => '1619784668']);
    $clientA = test()->createClient($orgA->id, ['member_id' => '4821234567']);
    $clientB = test()->createClient($orgB->id, ['member_id' => '4821234568']);

    billingClaimAuditRecord($orgA->id, $clientA->id, ['claim_number' => 'ORG-A']);
    billingClaimAuditRecord($orgB->id, $clientB->id, ['claim_number' => 'ORG-B']);

    $counts = app(BillingClaimAvailityStatusService::class)->syncPeriod(
        $orgA->id,
        \Carbon\Carbon::parse('2024-05-01')
    );

    expect($counts['synced'])->toBe(1);

    expect(BillingClaimAudit::withoutGlobalScopes()->where('claim_number', 'ORG-A')->first()->availity_status)->toBe('pending')
        ->and(BillingClaimAudit::withoutGlobalScopes()->where('claim_number', 'ORG-B')->first()->availity_status)->toBeNull();
});
