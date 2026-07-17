<?php

use App\Models\IntegrationCredential;
use App\Models\Organization;
use App\Models\User;
use App\Services\AgencyIdentityService;
use App\Services\CredentialVaultService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('agency identity service returns organization billing fields', function () {
    $org = Organization::create([
        'name' => 'Supportive Solutions HomeCare LLC',
        'status' => 'Active',
        'agency_npi' => '1619784667',
        'tax_id_ein' => '331930284',
        'medicaid_provider_id' => '1619784667',
        'legal_business_name' => 'Supportive Solutions HomeCare LLC',
    ]);

    $identity = app(AgencyIdentityService::class)->billingIdentity($org->id);

    expect($identity['npi'])->toBe('1619784667')
        ->and($identity['tax_id'])->toBe('331930284')
        ->and($identity['legal_name'])->toBe('Supportive Solutions HomeCare LLC');
});

test('super admin can update agency billing identity', function () {
    $org = Organization::create(['name' => 'Test Agency', 'status' => 'Active']);
    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->post(route('settings.global.agency'), [
            'name' => 'Supportive Solutions HomeCare LLC',
            'agency_npi' => '1619784667',
            'tax_id_ein' => '331930284',
            'medicaid_provider_id' => '1619784667',
            'legal_business_name' => 'Supportive Solutions HomeCare LLC',
            'legal_address_street' => '835 Mason St Suite C-116',
            'legal_address_city' => 'Dearborn',
            'legal_address_state' => 'MI',
            'legal_address_zip' => '48124',
        ])
        ->assertRedirect(route('settings.global', ['tab' => 'agency']));

    $org->refresh();
    expect($org->agency_npi)->toBe('1619784667')
        ->and($org->legal_address_city)->toBe('Dearborn');
});

test('credential vault encrypts and retrieves secrets', function () {
    $vault = app(CredentialVaultService::class);

    $vault->upsert(IntegrationCredential::KEY_AVAILITY, [
        'username' => 'availity-user',
        'api_key' => 'secret-demo-key',
    ]);

    expect($vault->username(IntegrationCredential::KEY_AVAILITY))->toBe('availity-user')
        ->and($vault->apiKey(IntegrationCredential::KEY_AVAILITY))->toBe('secret-demo-key');

    $stored = IntegrationCredential::where('key', IntegrationCredential::KEY_AVAILITY)->first();
    expect($stored->getRawOriginal('api_key'))->not->toBe('secret-demo-key');
});

test('super admin can save credential vault entries', function () {
    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    $payload = [
        'credentials' => collect(IntegrationCredential::supportedKeys())->keys()->map(fn ($key) => [
            'key' => $key,
            'username' => $key.'@example.com',
        ])->values()->all(),
    ];

    $this->actingAsWithTwoFactor($user)
        ->post(route('settings.global.credential-vault'), $payload)
        ->assertRedirect(route('settings.global', ['tab' => 'credential-vault']));

    expect(IntegrationCredential::where('key', IntegrationCredential::KEY_CHAMPS)->value('username'))->toBe('champs@example.com');
});

test('integration config service hydrates runtime config from credential vault metadata', function () {
    config([
        'services.availity.demo_key' => null,
        'services.availity.demo_secret' => null,
        'services.availity.default_payer_id' => null,
        'payroll.accountants_world_api_key' => null,
        'payroll.accountant_email' => null,
        'hha.client_id' => null,
    ]);

    app(CredentialVaultService::class)->upsert(IntegrationCredential::KEY_AVAILITY, [
        'metadata' => [
            'env' => 'demo',
            'demo_key' => 'vault-demo-id',
            'demo_secret' => 'vault-demo-secret',
            'default_payer_id' => 'BCBSF',
            'token_url' => 'https://api.availity.com/v1/token',
        ],
    ]);

    app(CredentialVaultService::class)->upsert(IntegrationCredential::KEY_ACCOUNTANTSWORLD, [
        'metadata' => [
            'app_id' => 'aw-app-id',
            'accountant_email' => 'accountant@example.com',
            'portal_url' => 'https://aw.example.com',
        ],
    ]);

    app(CredentialVaultService::class)->upsert(IntegrationCredential::KEY_HHA, [
        'metadata' => [
            'api_url' => 'https://hha.example.com',
            'client_id' => 'hha-client',
            'client_secret' => 'hha-secret',
            'attestation_status' => 'approved',
        ],
    ]);

    app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

    expect(config('services.availity.demo_key'))->toBe('vault-demo-id')
        ->and(config('services.availity.demo_secret'))->toBe('vault-demo-secret')
        ->and(config('services.availity.default_payer_id'))->toBe('BCBSF')
        ->and(config('payroll.accountants_world_app_id'))->toBe('aw-app-id')
        ->and(config('payroll.accountants_world_api_key'))->toBe('aw-app-id')
        ->and(config('payroll.accountant_email'))->toBe('accountant@example.com')
        ->and(config('hha.client_id'))->toBe('hha-client')
        ->and(config('hha.attestation_status'))->toBe('approved');
});

test('integration config service hydrates billing claims settings from global settings and sigma vault', function () {
    config([
        'billing_claims_audit.default_asw_email' => null,
        'billing_claims_audit.sigma_portal_url' => null,
    ]);

    app(\App\Services\GlobalSettingsService::class)->update([
        'billing.default_asw_email' => 'asw@mdhhs.example.gov',
        'billing.sigma_portal_url' => 'https://sigma.example.gov',
    ]);

    app(CredentialVaultService::class)->upsert(IntegrationCredential::KEY_SIGMA, [
        'username' => 'sigma-user',
        'password' => 'sigma-pass',
        'metadata' => [
            'portal_url' => 'https://vault-sigma.example.gov',
            'default_asw_email' => 'vault-asw@example.gov',
        ],
    ]);

    app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

    expect(config('billing_claims_audit.default_asw_email'))->toBe('asw@mdhhs.example.gov')
        ->and(config('billing_claims_audit.sigma_portal_url'))->toBe('https://sigma.example.gov');
});

test('integration config service falls back to sigma vault metadata for billing when global settings empty', function () {
    config([
        'billing_claims_audit.default_asw_email' => null,
        'billing_claims_audit.sigma_portal_url' => null,
    ]);

    app(CredentialVaultService::class)->upsert(IntegrationCredential::KEY_SIGMA, [
        'metadata' => [
            'portal_url' => 'https://vault-sigma.example.gov',
            'default_asw_email' => 'vault-asw@example.gov',
        ],
    ]);

    app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

    expect(config('billing_claims_audit.default_asw_email'))->toBe('vault-asw@example.gov')
        ->and(config('billing_claims_audit.sigma_portal_url'))->toBe('https://vault-sigma.example.gov');
});

test('integration config service hydrates state portal urls and docusign from vault', function () {
    config([
        'global_settings.vault_rpa' => config('global_settings.vault_rpa'),
        'docusign.integration_key' => null,
        'docusign.account_id' => null,
        'docusign.base_url' => null,
    ]);

    app(CredentialVaultService::class)->upsert(IntegrationCredential::KEY_CHAMPS, [
        'username' => 'champs-user',
        'password' => 'champs-pass',
        'metadata' => ['portal_url' => 'https://champs.example.gov'],
    ]);

    app(CredentialVaultService::class)->upsert(IntegrationCredential::KEY_DOCUSIGN, [
        'username' => 'docusign-account-guid',
        'api_key' => 'docusign-integration-key',
        'metadata' => ['base_url' => 'https://demo.docusign.net'],
    ]);

    app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

    expect(config('global_settings.vault_rpa.'.IntegrationCredential::KEY_CHAMPS.'.portal_url'))
        ->toBe('https://champs.example.gov')
        ->and(config('docusign.integration_key'))->toBe('docusign-integration-key')
        ->and(config('docusign.account_id'))->toBe('docusign-account-guid')
        ->and(config('docusign.base_url'))->toBe('https://demo.docusign.net');
});

test('super admin can save availity integration metadata via credential vault', function () {
    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->post(route('settings.global.credential-vault'), [
            'credentials' => [[
                'key' => IntegrationCredential::KEY_AVAILITY,
                'metadata' => [
                    'env' => 'demo',
                    'app_id' => '3938',
                    'demo_key' => 'demo-client-id',
                    'demo_secret' => 'demo-client-secret',
                    'default_payer_id' => 'BCBSF',
                    'token_url' => 'https://api.availity.com/v1/token',
                    'api_base_url' => 'https://api.availity.com/availity/v1',
                ],
            ]],
        ])
        ->assertRedirect(route('settings.global', ['tab' => 'credential-vault', 'integration' => IntegrationCredential::KEY_AVAILITY]));

    $stored = IntegrationCredential::where('key', IntegrationCredential::KEY_AVAILITY)->first();

    expect($stored)->not->toBeNull()
        ->and($stored->metadata['demo_key'])->toBe('demo-client-id')
        ->and($stored->metadata['default_payer_id'])->toBe('BCBSF')
        ->and(config('services.availity.demo_key'))->toBe('demo-client-id');
});
