<?php

use App\Models\IntegrationCredential;
use App\Models\User;
use App\Services\CredentialVaultService;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => seedModuleBasics());

test('hha vault form keeps client id and secret visible after save', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->post(route('settings.global.credential-vault'), [
            'credentials' => [[
                'key' => IntegrationCredential::KEY_HHA,
                'metadata' => [
                    'environment' => 'implementation',
                    'api_url' => 'https://implementation.hhaexchange.com',
                    'token_url' => 'https://implementation.hhaexchange.com/identity/connect/token',
                    'scope' => 'write:aggregator',
                    'client_id' => 'saved-hha-client-id',
                    'client_secret' => 'saved-hha-client-secret',
                    'attestation_status' => 'approved',
                    'provider_tax_id' => '331930284',
                    'payer_id' => 'MI73',
                ],
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $summary = app(CredentialVaultService::class)->summaryForView()[IntegrationCredential::KEY_HHA];

    expect($summary['configured'])->toBeTrue()
        ->and($summary['metadata']['api_url'])->toBe('https://implementation.hhaexchange.com')
        ->and($summary['metadata']['token_url'])->toBe('https://implementation.hhaexchange.com/identity/connect/token')
        ->and($summary['metadata']['scope'])->toBe('write:aggregator')
        ->and($summary['metadata']['client_id'])->toBe('saved-hha-client-id')
        ->and($summary['metadata']['client_secret'])->toBe('saved-hha-client-secret')
        ->and($summary['metadata']['attestation_status'])->toBe('approved');

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.global', ['tab' => 'credential-vault', 'integration' => IntegrationCredential::KEY_HHA]))
        ->assertOk()
        ->assertSee('saved-hha-client-id', false)
        ->assertSee('saved-hha-client-secret', false)
        ->assertSee('write:aggregator', false)
        ->assertSee('identity/connect/token', false);
});

test('hha test connection passes oauth when attestation is still pending', function () {
    Http::fake([
        'https://implementation.hhaexchange.com/identity/connect/token' => Http::response([
            'access_token' => 'draft-token',
            'expires_in' => 1800,
        ], 200),
    ]);

    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->postJson(route('settings.global.integrations.test'), [
            'slug' => IntegrationCredential::KEY_HHA,
            'draft' => [
                'username' => '',
                'password' => '',
                'api_key' => '',
                'metadata' => [
                    'api_url' => 'https://implementation.hhaexchange.com',
                    'token_url' => 'https://implementation.hhaexchange.com/identity/connect/token',
                    'scope' => 'write:aggregator',
                    'client_id' => 'draft-hha-client',
                    'client_secret' => 'draft-hha-secret',
                    'attestation_status' => 'pending',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('hha test connection applies partial draft over saved vault values', function () {
    Http::fake([
        'https://hha.example.com/identity/connect/token' => Http::response([
            'access_token' => 'saved-token',
            'expires_in' => 1800,
        ], 200),
    ]);

    app(CredentialVaultService::class)->upsert(IntegrationCredential::KEY_HHA, [
        'metadata' => [
            'api_url' => 'https://hha.example.com',
            'token_url' => 'https://hha.example.com/identity/connect/token',
            'scope' => 'write:aggregator',
            'client_id' => 'saved-client',
            'client_secret' => 'saved-secret',
            'attestation_status' => 'approved',
        ],
    ]);
    app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->postJson(route('settings.global.integrations.test'), [
            'slug' => IntegrationCredential::KEY_HHA,
            'draft' => [
                'username' => '',
                'password' => '',
                'api_key' => '',
                'metadata' => [
                    'client_id' => 'draft-only-client',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});
