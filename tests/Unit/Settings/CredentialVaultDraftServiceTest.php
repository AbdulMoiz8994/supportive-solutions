<?php

use App\Models\IntegrationCredential;
use App\Services\CredentialVaultDraftService;

test('credential vault draft service detects complete google workspace draft', function () {
    $service = app(CredentialVaultDraftService::class);

    $draft = $service->normalize([
        'username' => 'info@example.com',
        'metadata' => [
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'refresh_token' => 'refresh-token',
        ],
    ]);

    expect($draft)->not->toBeNull()
        ->and($service->isCompleteForTest(IntegrationCredential::KEY_GOOGLE_WORKSPACE, $draft))->toBeTrue();
});

test('credential vault draft service detects complete portal draft', function () {
    $service = app(CredentialVaultDraftService::class);

    $draft = $service->normalize([
        'username' => 'portal-user',
        'password' => 'portal-pass',
    ]);

    expect($draft)->not->toBeNull()
        ->and($service->isCompleteForTest(IntegrationCredential::KEY_SIGMA, $draft))->toBeTrue();
});

test('credential vault draft service rejects incomplete availity draft', function () {
    $service = app(CredentialVaultDraftService::class);

    $draft = $service->normalize([
        'metadata' => [
            'env' => 'demo',
            'token_url' => 'https://example.com/token',
            'api_base_url' => 'https://example.com/api',
            'demo_key' => 'demo-key',
        ],
    ]);

    expect($draft)->not->toBeNull()
        ->and($service->isCompleteForTest(IntegrationCredential::KEY_AVAILITY, $draft))->toBeFalse()
        ->and($service->hasContent($draft))->toBeTrue();
});

test('credential vault draft service detects content for partial hha draft', function () {
    $service = app(CredentialVaultDraftService::class);

    $draft = $service->normalize([
        'metadata' => [
            'client_id' => 'only-client-id',
        ],
    ]);

    expect($draft)->not->toBeNull()
        ->and($service->hasContent($draft))->toBeTrue()
        ->and($service->isCompleteForTest(IntegrationCredential::KEY_HHA, $draft))->toBeFalse();
});

test('credential vault draft service detects complete hha draft', function () {
    $service = app(CredentialVaultDraftService::class);

    $draft = $service->normalize([
        'metadata' => [
            'api_url' => 'https://implementation.hhaexchange.com',
            'client_id' => 'client',
            'client_secret' => 'secret',
            'scope' => 'write:aggregator',
        ],
    ]);

    expect($service->isCompleteForTest(IntegrationCredential::KEY_HHA, $draft))->toBeTrue();
});
