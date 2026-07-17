<?php

use App\Models\IntegrationCredential;
use App\Services\Availity\AvailityClaimPayloadMapper;
use App\Services\Availity\AvailityClient;
use App\Services\CredentialVaultService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    config([
        'services.availity.env' => 'demo',
        'services.availity.demo_key' => '',
        'services.availity.demo_secret' => 'vault-demo-secret',
        'services.availity.token_url' => 'https://api.availity.com/v1/token',
        'services.availity.api_base_url' => 'https://api.availity.com/availity/v1',
        'services.availity.request_type_code' => 'PRE_DETERMINATION',
        'services.availity.default_payer_id' => 'BCBSF',
        'services.availity.scope_demo' => 'healthcare-hipaa-transactions-demo',
    ]);
});

test('availity payload mapper converts internal claim to professional claims schema', function () {
    $mapped = app(AvailityClaimPayloadMapper::class)->toProfessionalClaim([
        'referenceNumber' => 'PR-42-2026-05',
        'billingProvider' => [
            'npi' => '1619784667',
            'taxId' => '33-1930284',
            'medicaidProviderId' => 'AW-MI-0883',
            'organizationName' => 'Supportive Solutions HomeCare LLC',
        ],
        'patient' => [
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'memberId' => '4821234567',
        ],
        'serviceLines' => [[
            'procedureCode' => 'T1019',
            'units' => 40,
            'chargeAmount' => 1200.00,
            'serviceDateFrom' => '2026-05-01',
            'serviceDateTo' => '2026-05-31',
        ]],
    ]);

    expect($mapped['requestTypeCode'])->toBe('PRE_DETERMINATION')
        ->and($mapped['billingProvider']['npi'])->toBe('1619784667')
        ->and($mapped['billingProvider']['ein'])->toBe('331930284')
        ->and($mapped['payer']['id'])->toBe('BCBSF')
        ->and($mapped['subscriber']['memberId'])->toBe('4821234567')
        ->and($mapped['claimInformation']['serviceLines'][0]['procedureCode'])->toBe('T1019')
        ->and($mapped['customerId'])->toBe('PR-42-2026-05');
});

test('availity client obtains oauth token and submits professional claim with vault client id', function () {
    app(CredentialVaultService::class)->upsert(IntegrationCredential::KEY_AVAILITY, [
        'metadata' => [
            'demo_key' => 'vault-demo-key',
            'demo_secret' => 'vault-demo-secret',
            'token_url' => 'https://api.availity.com/v1/token',
            'api_base_url' => 'https://api.availity.com/availity/v1',
            'scope_demo' => 'healthcare-hipaa-transactions-demo',
            'request_type' => 'PRE_DETERMINATION',
        ],
    ]);

    app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

    Http::fake([
        'https://api.availity.com/v1/token' => Http::response([
            'access_token' => 'vault-bearer-token',
            'token_type' => 'Bearer',
            'expires_in' => 300,
        ]),
        'https://api.availity.com/availity/v1/professional-claims' => Http::response([], 202, [
            'Location' => 'https://api.availity.com/availity/v1/professional-claims/1684335841477061460',
        ]),
    ]);

    $client = app(AvailityClient::class);
    expect($client->clientId())->toBe('vault-demo-key');

    $result = $client->submitClaim([
        'referenceNumber' => 'TEST-1',
        'billingProvider' => ['npi' => '1619784667', 'taxId' => '331930284'],
        'patient' => ['firstName' => 'Jane', 'lastName' => 'Doe', 'memberId' => '12345'],
        'serviceLines' => [[
            'procedureCode' => 'T1019',
            'units' => 4,
            'chargeAmount' => 120,
            'serviceDateFrom' => '2026-05-01',
            'serviceDateTo' => '2026-05-31',
        ]],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['claim_id'])->toBe('1684335841477061460')
        ->and($result['status'])->toBe('pending');

    Http::assertSent(function ($request) {
        if ($request->url() === 'https://api.availity.com/v1/token') {
            return $request->method() === 'POST'
                && $request->data()['grant_type'] === 'client_credentials'
                && $request->data()['client_id'] === 'vault-demo-key'
                && $request->data()['client_secret'] === 'vault-demo-secret';
        }

        return $request->url() === 'https://api.availity.com/availity/v1/professional-claims'
            && $request->hasHeader('Authorization', 'Bearer vault-bearer-token')
            && ($request->data()['requestTypeCode'] ?? null) === 'PRE_DETERMINATION';
    });
});

test('availity client check claim status parses 202 processing response', function () {
    config([
        'services.availity.demo_key' => 'demo-key',
        'services.availity.demo_secret' => 'demo-secret',
    ]);

    Http::fake([
        'https://api.availity.com/v1/token' => Http::response([
            'access_token' => 'demo-bearer-token',
            'token_type' => 'Bearer',
            'expires_in' => 300,
        ]),
        'https://api.availity.com/availity/v1/professional-claims/*' => Http::response([], 202, [
            'X-Status-Message' => 'We are processing your request.',
        ]),
    ]);

    $result = app(AvailityClient::class)->checkClaimStatus('AV-TEST-200');

    expect($result['success'])->toBeTrue()
        ->and($result['claim_id'])->toBe('AV-TEST-200')
        ->and($result['status'])->toBe('pending');
});

test('availity client caches oauth token until expiry window', function () {
    config([
        'services.availity.demo_key' => 'demo-key',
        'services.availity.demo_secret' => 'demo-secret',
        'services.availity.token_cache_seconds' => 240,
    ]);

    Http::fake([
        'https://api.availity.com/v1/token' => Http::response([
            'access_token' => 'cached-token',
            'token_type' => 'Bearer',
            'expires_in' => 300,
        ]),
    ]);

    $client = app(AvailityClient::class);

    expect($client->accessToken())->toBe('cached-token')
        ->and($client->accessToken())->toBe('cached-token');

    Http::assertSentCount(1);
});

test('availity client submit returns failure on 403 forbidden', function () {
    config([
        'services.availity.demo_key' => 'demo-key',
        'services.availity.demo_secret' => 'demo-secret',
    ]);

    Http::fake([
        'https://api.availity.com/v1/token' => Http::response([
            'access_token' => 'demo-bearer-token',
            'expires_in' => 300,
        ]),
        'https://api.availity.com/availity/v1/professional-claims' => Http::response(['error' => 'Forbidden'], 403),
    ]);

    $result = app(AvailityClient::class)->submitClaim([
        'referenceNumber' => 'DENIED-1',
        'billingProvider' => ['npi' => '1619784667', 'taxId' => '331930284'],
        'patient' => ['firstName' => 'Jane', 'lastName' => 'Doe', 'memberId' => '12345'],
        'serviceLines' => [[
            'procedureCode' => 'T1019',
            'units' => 4,
            'chargeAmount' => 120,
            'serviceDateFrom' => '2026-05-01',
            'serviceDateTo' => '2026-05-31',
        ]],
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['claim_id'])->toBeNull()
        ->and($result['status'])->toBe('failed');
});

test('availity client inquire claim status handles API validation errors', function () {
    config([
        'services.availity.demo_key' => 'demo-key',
        'services.availity.demo_secret' => 'demo-secret',
    ]);

    Http::fake([
        'https://api.availity.com/v1/token' => Http::response(['access_token' => 't', 'expires_in' => 300]),
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'userMessage' => 'Missing required field payer.id',
        ], 400),
    ]);

    $result = app(AvailityClient::class)->inquireClaimStatus(['claimNumber' => 'BAD']);

    expect($result['success'])->toBeFalse()
        ->and($result['status'])->toBe('failed')
        ->and($result['message'])->toContain('Missing required field');
});

test('availity client inquire claim status handles 202 async response', function () {
    config([
        'services.availity.demo_key' => 'demo-key',
        'services.availity.demo_secret' => 'demo-secret',
    ]);

    Http::fake([
        'https://api.availity.com/v1/token' => Http::response(['access_token' => 't', 'expires_in' => 300]),
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([], 202, [
            'Location' => 'https://api.availity.com/availity/v1/claim-statuses/ASYNC-99',
            'X-Status-Message' => 'Processing inquiry',
        ]),
    ]);

    $result = app(AvailityClient::class)->inquireClaimStatus(['claimNumber' => 'ASYNC-1']);

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('pending')
        ->and($result['reference_id'])->toBe('ASYNC-99')
        ->and($result['message'])->toBe('Processing inquiry');
});

test('availity client inquire claim status defaults when claimStatuses array is empty', function () {
    config([
        'services.availity.demo_key' => 'demo-key',
        'services.availity.demo_secret' => 'demo-secret',
    ]);

    Http::fake([
        'https://api.availity.com/v1/token' => Http::response(['access_token' => 't', 'expires_in' => 300]),
        'https://api.availity.com/availity/v1/claim-statuses' => Http::response([
            'totalCount' => 0,
            'claimStatuses' => [],
        ], 200),
    ]);

    $result = app(AvailityClient::class)->inquireClaimStatus(['claimNumber' => 'EMPTY-1']);

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('pending');
});

test('availity client check claim status parses 200 approved response', function () {
    config([
        'services.availity.demo_key' => 'demo-key',
        'services.availity.demo_secret' => 'demo-secret',
    ]);

    Http::fake([
        'https://api.availity.com/v1/token' => Http::response(['access_token' => 't', 'expires_in' => 300]),
        'https://api.availity.com/availity/v1/professional-claims/*' => Http::response([
            'id' => 'AV-APPROVED',
            'status' => 'approved',
        ], 200),
    ]);

    $result = app(AvailityClient::class)->checkClaimStatus('AV-APPROVED');

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('approved')
        ->and($result['claim_id'])->toBe('AV-APPROVED');
});
