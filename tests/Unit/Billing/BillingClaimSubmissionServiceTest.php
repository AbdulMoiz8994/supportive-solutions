<?php

use App\Models\BillingClaimAudit;
use App\Services\Billing\BillingClaimSubmissionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    availityTestConfig();
});

test('shouldSubmitViaAvaility is true only for MICH Availity-routed claims', function () {
    $service = app(BillingClaimSubmissionService::class);

    $availityClaim = new BillingClaimAudit([
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'submission_channel' => '837P - Availity',
    ]);
    $dhsClaim = new BillingClaimAudit([
        'program_type' => BillingClaimAudit::PROGRAM_DHS,
        'submission_channel' => '837P - Availity',
    ]);
    $paperClaim = new BillingClaimAudit([
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'submission_channel' => 'Paper',
    ]);

    expect($service->shouldSubmitViaAvaility($availityClaim))->toBeTrue()
        ->and($service->shouldSubmitViaAvaility($dhsClaim))->toBeFalse()
        ->and($service->shouldSubmitViaAvaility($paperClaim))->toBeFalse();
});

test('buildAvailityPayload includes agency identity client and service line totals', function () {
    $org = test()->createOrganization([
        'agency_npi' => '1619784667',
        'tax_id_ein' => '331930284',
        'medicaid_provider_id' => 'AW-MI-0883',
        'legal_business_name' => 'Supportive Solutions HomeCare LLC',
    ]);
    $employee = test()->createEmployee($org->id, ['first_name' => 'Sam', 'last_name' => 'Care']);
    $client = test()->createClient($org->id, [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'member_id' => '4821234567',
        'billing_rate' => 30,
    ]);

    $audit = billingClaimAuditRecord($org->id, $client->id, [
        'employee_id' => $employee->id,
        'claim_number' => '837P-PAYLOAD-1',
        'total_hours' => 108,
        'verified_hours' => 100,
        'hourly_rate' => 30,
        'total_amount' => 3000,
        'service_code' => 'T019',
    ]);

    $payload = app(BillingClaimSubmissionService::class)->buildAvailityPayload($audit);

    expect($payload['claimType'])->toBe('837P')
        ->and($payload['referenceNumber'])->toBe('837P-PAYLOAD-1')
        ->and($payload['billingProvider']['npi'])->toBe('1619784667')
        ->and($payload['billingProvider']['taxId'])->toBe('331930284')
        ->and($payload['patient']['memberId'])->toBe('4821234567')
        ->and($payload['renderingProvider']['lastName'])->toBe('Care')
        ->and($payload['serviceLines'][0]['procedureCode'])->toBe('T019')
        ->and($payload['serviceLines'][0]['hours'])->toBe(108.0)
        ->and($payload['serviceLines'][0]['units'])->toBe(432)
        ->and($payload['totals']['chargeAmount'])->toBe(3000.0);
});

test('submit stores availity reference and status on successful 202 response', function () {
    availityHttpFake([
        'https://api.availity.com/availity/v1/professional-claims' => Http::response([], 202, [
            'Location' => 'https://api.availity.com/availity/v1/professional-claims/REF-837P-99',
        ]),
    ]);

    $org = test()->createOrganization(['agency_npi' => '1619784667', 'tax_id_ein' => '331930284']);
    $client = test()->createClient($org->id, ['member_id' => '4821234567']);

    $audit = billingClaimAuditRecord($org->id, $client->id, [
        'claim_number' => '837P-SUBMIT-OK',
        'availity_reference_id' => null,
        'availity_status' => null,
    ]);

    $result = app(BillingClaimSubmissionService::class)->submit($audit);

    expect($result['success'])->toBeTrue()
        ->and($result['claim_id'])->toBe('REF-837P-99');

    $audit->refresh();
    expect($audit->availity_reference_id)->toBe('REF-837P-99')
        ->and($audit->payer_reference)->toBe('REF-837P-99')
        ->and($audit->availity_status)->toBe('pending')
        ->and($audit->availity_status_checked_at)->not->toBeNull();
});

test('submit returns failure and does not store reference on 403 forbidden', function () {
    availityHttpFake([
        'https://api.availity.com/availity/v1/professional-claims' => Http::response(['error' => 'Forbidden'], 403),
    ]);

    $org = test()->createOrganization(['agency_npi' => '1619784667', 'tax_id_ein' => '331930284']);
    $client = test()->createClient($org->id, ['member_id' => '4821234567']);

    $audit = billingClaimAuditRecord($org->id, $client->id, ['claim_number' => '837P-SUBMIT-FAIL']);

    $result = app(BillingClaimSubmissionService::class)->submit($audit);

    expect($result['success'])->toBeFalse()
        ->and($result['claim_id'])->toBeNull();

    $audit->refresh();
    expect($audit->availity_reference_id)->toBeNull()
        ->and($audit->availity_status)->toBeNull();
});

test('submit skips non-Availity claims without calling the API', function () {
    Http::fake();

    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $audit = billingClaimAuditRecord($org->id, $client->id, [
        'program_type' => BillingClaimAudit::PROGRAM_DHS,
        'submission_channel' => 'Paper',
    ]);

    $result = app(BillingClaimSubmissionService::class)->submit($audit);

    expect($result['success'])->toBeFalse()
        ->and($result['status'])->toBe('skipped');

    Http::assertNothingSent();
});
