<?php

use App\Services\HHA\HHAPhase1TestService;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => seedModuleBasics());

test('hha phase1 test 1-001 authenticates via identity connect token', function () {
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
            'access_token' => 'phase1-token',
            'expires_in' => 1800,
        ], 200),
    ]);

    $result = app(HHAPhase1TestService::class)->run('1-001');

    expect($result['success'])->toBeTrue()
        ->and($result['scenario'])->toBe('1-001');
});

test('hha phase1 test 9-001 expects http 400 when provider tax id missing', function () {
    config([
        'hha.api_url' => 'https://implementation.hhaexchange.com',
        'hha.token_url' => 'https://implementation.hhaexchange.com/identity/connect/token',
        'hha.client_id' => 'hha-id',
        'hha.client_secret' => 'hha-secret',
        'hha.attestation_status' => 'approved',
        'hha.provider_tax_id' => '331930284',
    ]);

    Http::fake([
        'https://implementation.hhaexchange.com/identity/connect/token' => Http::response([
            'access_token' => 'phase1-token',
            'expires_in' => 1800,
        ], 200),
        'https://implementation.hhaexchange.com/api/v2/caregivers' => Http::response([
            'transactionId' => 'tx-err',
            'message' => 'providerTaxId required',
        ], 400),
    ]);

    $org = $this->createOrganization(['tax_id_ein' => '331930284']);
    $employee = $this->createEmployee($org->id, [
        'date_of_birth' => '1985-09-19',
        'hire_date' => '2020-10-01',
    ]);

    $result = app(HHAPhase1TestService::class)->run('9-001', ['employee_id' => $employee->id]);

    expect($result['success'])->toBeTrue()
        ->and($result['scenario'])->toBe('9-001');
});
