<?php

use App\Models\BillingClaimAudit;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    availityTestConfig();
});

test('generate and submit creates claims from completed visits before submitting', function () {
    $org = $this->createOrganization(['agency_npi' => '1619784667']);
    $client = $this->createClient($org->id, ['member_id' => '4821234567', 'billing_rate' => 30]);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $employee = $this->createEmployee($org->id);

    billingActiveAuthorization($org->id, $client->id, [
        'start_date' => '2024-01-01',
        'end_date' => '2024-12-31',
    ]);

    $this->createSchedule($org->id, $client->id, $employee->id, [
        'date' => '2024-05-10',
        'total_hours' => 4,
        'status' => Schedule::STATUS_COMPLETED,
        'evv_status' => true,
    ]);

    availityHttpFake([
        'https://api.availity.com/availity/v1/professional-claims' => Http::response([], 202, [
            'Location' => 'https://api.availity.com/availity/v1/professional-claims/REF-GEN-NEW',
        ]),
        'https://api.availity.com/availity/v1/professional-claims/*' => Http::response(['status' => 'submitted'], 200),
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'submitted']],
        ], 200),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.generate-submit'), ['period' => '2024-05'])
        ->assertRedirect(route('billing-claims-audit.index', ['period' => '2024-05']))
        ->assertSessionHas('success');

    $claim = BillingClaimAudit::withoutGlobalScopes()
        ->where('organization_id', $org->id)
        ->where('client_id', $client->id)
        ->whereYear('billing_period', 2024)
        ->whereMonth('billing_period', 5)
        ->first();

    expect($claim)->not->toBeNull()
        ->and($claim->submitted_at)->not->toBeNull()
        ->and((float) $claim->total_hours)->toBe(4.0)
        ->and($claim->availity_reference_id)->toBe('REF-GEN-NEW');
});

test('availity submission failure does not mark claim as submitted', function () {
    $org = $this->createOrganization(['agency_npi' => '1619784667']);
    $client = $this->createClient($org->id, ['member_id' => '4821234567']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    availityHttpFake([
        'https://api.availity.com/availity/v1/professional-claims' => Http::response(['error' => 'Forbidden'], 403),
    ]);

    $claim = billingClaimAuditRecord($org->id, $client->id, [
        'submitted_at' => null,
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
        'billing_status' => BillingClaimAudit::BILLING_READY,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.generate-submit'), ['period' => '2024-05'])
        ->assertSessionHas('warning');

    expect($claim->fresh()->submitted_at)->toBeNull();
});

test('authorized user can submit single claim to availity from detail page', function () {
    $org = $this->createOrganization(['agency_npi' => '1619784667']);
    $client = $this->createClient($org->id, ['member_id' => '4821234567']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    availityHttpFake([
        'https://api.availity.com/availity/v1/professional-claims' => Http::response([], 202, [
            'Location' => 'https://api.availity.com/availity/v1/professional-claims/REF-SINGLE',
        ]),
        'https://api.availity.com/availity/v1/professional-claims/*' => Http::response(['status' => 'submitted'], 200),
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 1,
            'claimStatuses' => [['status' => 'submitted']],
        ], 200),
    ]);

    $claim = billingClaimAuditRecord($org->id, $client->id, [
        'submitted_at' => null,
        'billing_status' => BillingClaimAudit::BILLING_READY,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('billing-claims-audit.submit', $claim))
        ->assertRedirect(route('billing-claims-audit.show', $claim))
        ->assertSessionHas('success');

    expect($claim->fresh()->submitted_at)->not->toBeNull()
        ->and($claim->fresh()->availity_reference_id)->toBe('REF-SINGLE');
});

test('sigma portal link redirects to configured portal url', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $claim = billingClaimAuditRecord($org->id, $client->id, [
        'program_type' => BillingClaimAudit::PROGRAM_DHS,
        'submission_channel' => 'Home Help - Sigma Portal',
    ]);

    config(['billing_claims_audit.sigma_portal_url' => 'https://sigma.example.gov']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.sigma-portal', $claim))
        ->assertRedirect('https://sigma.example.gov');
});
