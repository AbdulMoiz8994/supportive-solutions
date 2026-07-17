<?php

use App\Services\HHA\HHAExchangeClient;
use Illuminate\Support\Facades\Http;

/**
 * Regression: HHA connection status must report pending_attestation when credentials
 * exist and OAuth works but attestation is not approved — not missing_credentials.
 * OAuth is still attempted (same as Swagger Authorize / Test 1-001).
 */
test('hha reports pending attestation before missing credentials when oauth succeeds', function () {
    config([
        'hha.attestation_status' => 'pending',
        'hha.client_id' => 'configured-id',
        'hha.client_secret' => 'configured-secret',
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

    expect($status['status'])->toBe('pending_attestation')
        ->and($status['oauth_ok'])->toBeTrue()
        ->and($status['connected'])->toBeFalse();
});

test('hha reports missing credentials only when creds are absent', function () {
    config([
        'hha.attestation_status' => 'pending',
        'hha.client_id' => null,
        'hha.client_secret' => null,
    ]);

    $status = app(HHAExchangeClient::class)->getConnectionStatus();

    expect($status['status'])->toBe('missing_credentials');
});
