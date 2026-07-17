<?php

use App\Models\GlobalIntegrationHealth;
use App\Models\IntegrationCredential;
use App\Models\User;
use App\Services\GlobalSettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('super admin can test accountantsworld integration connectivity', function () {
    Http::fake([
        '*' => Http::response('ok', 200),
    ]);

    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    IntegrationCredential::create([
        'key' => IntegrationCredential::KEY_ACCOUNTANTSWORLD,
        'api_key' => 'c9c999aa4fc04b14a4f371aad354424e',
        'metadata' => ['app_id' => 'c9c999aa4fc04b14a4f371aad354424e'],
    ]);

    $this->actingAsWithTwoFactor($user)
        ->postJson(route('settings.global.integrations.test'), [
            'slug' => 'accountantsworld',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('slug', 'accountantsworld')
        ->assertJsonStructure([
            'checks' => [['name', 'passed', 'detail']],
            'latency_ms',
            'summary',
        ]);

    $this->assertDatabaseHas('global_integration_health', [
        'slug' => 'accountantsworld',
        'status' => GlobalIntegrationHealth::STATUS_CONNECTED,
    ]);
});

test('super admin can test vault credential by key', function () {
    Http::fake([
        'https://milogin.michigan.gov' => Http::response('', 200),
        '*' => Http::response('', 200),
    ]);

    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    IntegrationCredential::create([
        'key' => IntegrationCredential::KEY_CHAMPS,
        'username' => 'sshc.rpa',
        'password' => 'secret-password',
    ]);

    $this->actingAsWithTwoFactor($user)
        ->postJson(route('settings.global.integrations.test'), [
            'slug' => IntegrationCredential::KEY_CHAMPS,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'checks' => [['name', 'passed', 'detail']],
            'latency_ms',
            'summary',
        ]);
});

test('integration test returns missing credentials message when not configured', function () {
    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->postJson(route('settings.global.integrations.test'), [
            'slug' => 'ringcentral',
        ])
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('status', GlobalIntegrationHealth::STATUS_NOT_CONFIGURED);
});

test('non super admin cannot test integrations', function () {
    $user = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->postJson(route('settings.global.integrations.test'), [
            'slug' => 'availity',
        ])
        ->assertForbidden();
});

test('invalid integration slug is rejected', function () {
    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->postJson(route('settings.global.integrations.test'), [
            'slug' => 'not-a-real-integration',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

test('global settings integrations page shows test connection buttons', function () {
    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->get(route('settings.global', ['tab' => 'integrations']))
        ->assertOk()
        ->assertSee('Test connection', false)
        ->assertSee('Connected systems', false);
});

test('super admin can save billing claims settings and test all billing connections', function () {
    Http::fake([
        '*' => Http::response('', 200),
    ]);

    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    IntegrationCredential::create([
        'key' => IntegrationCredential::KEY_AVAILITY,
        'api_key' => 'demo-key',
        'password' => 'demo-secret',
        'metadata' => [
            'env' => 'demo',
            'demo_key' => 'demo-key',
            'demo_secret' => 'demo-secret',
            'token_url' => 'https://api.availity.com/v1/token',
            'api_base_url' => 'https://api.availity.com/availity/v1',
        ],
    ]);

    IntegrationCredential::create([
        'key' => IntegrationCredential::KEY_GOOGLE_WORKSPACE,
        'username' => 'billing@example.com',
        'api_key' => 'google-client-id',
        'password' => 'google-client-secret',
        'metadata' => [
            'refresh_token' => 'refresh-token',
        ],
    ]);

    IntegrationCredential::create([
        'key' => IntegrationCredential::KEY_SIGMA,
        'username' => 'sigma-user',
        'password' => 'sigma-pass',
        'metadata' => [
            'portal_url' => 'https://sigma.example.gov',
        ],
    ]);

    $this->actingAsWithTwoFactor($user)
        ->post(route('settings.global.update'), [
            '_tab' => 'billing-claims',
            'billing' => [
                'default_asw_email' => 'asw@mdhhs.example.gov',
                'sigma_portal_url' => 'https://sigma.example.gov',
            ],
        ])
        ->assertRedirect(route('settings.global', ['tab' => 'billing-claims']));

    expect(app(GlobalSettingsService::class)->get('billing.default_asw_email'))->toBe('asw@mdhhs.example.gov');

    app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

    expect(config('billing_claims_audit.default_asw_email'))->toBe('asw@mdhhs.example.gov')
        ->and(config('billing_claims_audit.sigma_portal_url'))->toBe('https://sigma.example.gov');

    $this->actingAsWithTwoFactor($user)
        ->postJson(route('settings.global.integrations.test'), [
            'slug' => 'billing-claims',
        ])
        ->assertOk()
        ->assertJsonPath('slug', 'billing-claims')
        ->assertJsonStructure([
            'checks' => [['name', 'passed', 'detail']],
            'latency_ms',
            'summary',
        ]);

    $this->assertDatabaseHas('global_integration_health', [
        'slug' => 'billing-claims',
    ]);
});

test('billing claims settings page shows submission channels and test buttons', function () {
    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->get(route('settings.global', ['tab' => 'billing-claims']))
        ->assertOk()
        ->assertSee('Billing submission settings', false)
        ->assertSee('Test all billing connections', false)
        ->assertSee('MICH 837P · Availity', false);
});
