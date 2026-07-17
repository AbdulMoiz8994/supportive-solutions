<?php

use App\Services\HHA\HHAExchangeClient;
use App\Services\HHA\HHASyncService;
use Illuminate\Support\Facades\Http;

test('hha client reports pending attestation when oauth works but attestation not approved', function () {
    config([
        'hha.attestation_status' => 'pending',
        'hha.client_id' => 'test-client-id',
        'hha.client_secret' => 'test-client-secret',
        'hha.token_url' => 'https://implementation.hhaexchange.com/identity/connect/token',
        'hha.scope' => 'write:aggregator',
    ]);

    Http::fake([
        'https://implementation.hhaexchange.com/identity/connect/token' => Http::response([
            'access_token' => 'token-123',
            'expires_in' => 1800,
        ], 200),
    ]);

    $status = app(HHAExchangeClient::class)->getConnectionStatus();

    expect($status['connected'])->toBeFalse()
        ->and($status['oauth_ok'])->toBeTrue()
        ->and($status['status'])->toBe('pending_attestation');
});

test('hha export visit returns pending when attestation not approved even if oauth works', function () {
    config([
        'hha.attestation_status' => 'pending',
        'hha.client_id' => 'test-client-id',
        'hha.client_secret' => 'test-client-secret',
        'hha.token_url' => 'https://implementation.hhaexchange.com/identity/connect/token',
    ]);

    Http::fake([
        'https://implementation.hhaexchange.com/identity/connect/token' => Http::response([
            'access_token' => 'token-123',
            'expires_in' => 1800,
        ], 200),
    ]);

    $result = app(HHAExchangeClient::class)->exportVisit(['visit_id' => 1]);

    expect($result['success'])->toBeFalse()
        ->and($result['status'])->toBe('pending_attestation');
});

test('hha connection test acquires oauth token with scope write aggregator', function () {
    config([
        'hha.api_url' => 'https://implementation.hhaexchange.com',
        'hha.token_url' => 'https://implementation.hhaexchange.com/identity/connect/token',
        'hha.client_id' => 'hha-id',
        'hha.client_secret' => 'hha-secret',
        'hha.scope' => 'write:aggregator',
        'hha.attestation_status' => 'approved',
    ]);

    Http::fake([
        'https://implementation.hhaexchange.com/identity/connect/token' => Http::response([
            'access_token' => 'token-123',
            'expires_in' => 1800,
            'token_type' => 'Bearer',
        ], 200),
    ]);

    $status = app(HHAExchangeClient::class)->getConnectionStatus();

    expect($status['connected'])->toBeTrue()
        ->and($status['status'])->toBe('connected');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://implementation.hhaexchange.com/identity/connect/token'
            && $request['grant_type'] === 'client_credentials'
            && $request['scope'] === 'write:aggregator'
            && $request['client_id'] === 'hha-id'
            && $request['client_secret'] === 'hha-secret';
    });
});

test('hha sync caregiver posts to v2 api when connected', function () {
    config([
        'hha.api_url' => 'https://implementation.hhaexchange.com',
        'hha.token_url' => 'https://implementation.hhaexchange.com/identity/connect/token',
        'hha.client_id' => 'hha-id',
        'hha.client_secret' => 'hha-secret',
        'hha.scope' => 'write:aggregator',
        'hha.attestation_status' => 'approved',
        'hha.endpoints.caregivers' => '/api/v2/caregivers',
    ]);

    Http::fake([
        'https://implementation.hhaexchange.com/identity/connect/token' => Http::response([
            'access_token' => 'token-123',
            'expires_in' => 1800,
        ]),
        'https://implementation.hhaexchange.com/api/v2/caregivers' => Http::response([
            'transactionId' => 'tx-cg-1',
        ], 200),
    ]);

    $result = app(HHAExchangeClient::class)->syncCaregiver([
        'providerTaxId' => '331930284',
        'qualifier' => 'ExternalID',
        'externalID' => '7',
        'ssn' => '999999999',
        'dateOfBirth' => '1985-09-19',
        'lastName' => 'Doe',
        'firstName' => 'John',
        'gender' => 'Other',
        'type' => 'Both',
        'professionalLicenseNumber' => '999999999999',
        'hireDate' => '2020-10-01',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['transaction_id'])->toBe('tx-cg-1')
        ->and($result['http_status'])->toBe(200);
});

test('hha export visit accepts 202 and returns transaction id', function () {
    config([
        'hha.api_url' => 'https://implementation.hhaexchange.com',
        'hha.token_url' => 'https://implementation.hhaexchange.com/identity/connect/token',
        'hha.client_id' => 'hha-id',
        'hha.client_secret' => 'hha-secret',
        'hha.attestation_status' => 'approved',
        'hha.endpoints.visits' => '/api/v2/visits',
    ]);

    Http::fake([
        'https://implementation.hhaexchange.com/identity/connect/token' => Http::response([
            'access_token' => 'token-123',
            'expires_in' => 1800,
        ]),
        'https://implementation.hhaexchange.com/api/v2/visits' => Http::response([
            'transactionId' => 'tx-visit-1',
        ], 202),
    ]);

    $result = app(HHAExchangeClient::class)->exportVisit([
        'visits' => [['externalVisitId' => '1']],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['transaction_id'])->toBe('tx-visit-1')
        ->and($result['http_status'])->toBe(202);
});

test('hha get transaction returns evvmsid', function () {
    config([
        'hha.api_url' => 'https://implementation.hhaexchange.com',
        'hha.token_url' => 'https://implementation.hhaexchange.com/identity/connect/token',
        'hha.client_id' => 'hha-id',
        'hha.client_secret' => 'hha-secret',
        'hha.attestation_status' => 'approved',
    ]);

    Http::fake([
        'https://implementation.hhaexchange.com/identity/connect/token' => Http::response([
            'access_token' => 'token-123',
            'expires_in' => 1800,
        ]),
        'https://implementation.hhaexchange.com/api/v2/visits/transactions/tx-1' => Http::response([
            'evvmsid' => 'evv-uuid-1',
        ], 200),
    ]);

    $result = app(HHAExchangeClient::class)->getTransaction('tx-1');

    expect($result['success'])->toBeTrue()
        ->and($result['evvmsid'])->toBe('evv-uuid-1');
});

test('hha normalizes api base url by stripping api version path', function () {
    $client = app(HHAExchangeClient::class);

    expect($client->normalizeApiBaseUrl('https://implementation.hhaexchange.com/api/v2/'))
        ->toBe('https://implementation.hhaexchange.com')
        ->and($client->normalizeApiBaseUrl('https://implementation.hhaexchange.com/api/v1'))
        ->toBe('https://implementation.hhaexchange.com')
        ->and($client->normalizeApiBaseUrl('https://implementation.hhaexchange.com'))
        ->toBe('https://implementation.hhaexchange.com');
});

test('hha client reports missing credentials when attestation approved but no creds', function () {
    config([
        'hha.attestation_status' => 'approved',
        'hha.client_id' => null,
        'hha.client_secret' => null,
    ]);

    $status = app(HHAExchangeClient::class)->getConnectionStatus();

    expect($status['connected'])->toBeFalse()
        ->and($status['status'])->toBe('missing_credentials');
});


test('hha sync service builds official caregiver payload shape', function () {
    $org = test()->createOrganization([
        'tax_id_ein' => '331930284',
        'agency_npi' => '1619784667',
    ]);
    $employee = test()->createEmployee($org->id, [
        'first_name' => 'Jane',
        'last_name' => 'Care',
        'date_of_birth' => '1990-05-01',
        'hire_date' => '2020-10-01',
        'gender' => 'Female',
    ]);

    config([
        'hha.provider_tax_id' => '331930284',
        'hha.payer_id' => 'MI73',
    ]);

    $payload = app(HHASyncService::class)->buildCaregiverPayload($employee);

    expect($payload)->toHaveKeys([
        'providerTaxId',
        'qualifier',
        'externalID',
        'ssn',
        'dateOfBirth',
        'lastName',
        'firstName',
        'gender',
        'type',
        'professionalLicenseNumber',
        'hireDate',
    ])
        ->and($payload['qualifier'])->toBe('ExternalID')
        ->and($payload['type'])->toBe('Both')
        ->and($payload['ssn'])->toBe('999999999')
        ->and($payload['hireDate'])->toBe('2020-10-01');
});
