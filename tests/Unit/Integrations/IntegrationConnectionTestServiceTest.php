<?php

use App\Models\GlobalIntegrationHealth;
use App\Models\IntegrationCredential;
use App\Services\Directory\IntegrationConnectionTestService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('portal credential test fails when vault login is incomplete', function () {
    $result = app(IntegrationConnectionTestService::class)
        ->testCredentialKey(IntegrationCredential::KEY_CHAMPS);

    $payload = $result->toArray();

    expect($payload['success'])->toBeFalse()
        ->and($payload['status'])->toBe(GlobalIntegrationHealth::STATUS_NOT_CONFIGURED)
        ->and(collect($payload['checks'])->pluck('name'))->toContain('Portal login', 'Portal password')
        ->and($payload['recommendation'])->toContain('Credential Vault');
});

test('portal credential test passes when credentials exist and portal is reachable', function () {
    Http::fake([
        'https://milogin.michigan.gov' => Http::response('', 200),
    ]);

    IntegrationCredential::create([
        'key' => IntegrationCredential::KEY_CHAMPS,
        'username' => 'sshc.rpa',
        'password' => 'secret-password',
    ]);

    $payload = app(IntegrationConnectionTestService::class)
        ->testCredentialKey(IntegrationCredential::KEY_CHAMPS)
        ->toArray();

    expect($payload['success'])->toBeTrue()
        ->and($payload['status'])->toBe(GlobalIntegrationHealth::STATUS_CONNECTED)
        ->and(collect($payload['checks'])->every(fn (array $check) => $check['passed']))->toBeTrue()
        ->and($payload['summary'])->toMatch('/3\/3 checks passed/');
});

test('portal credential test fails when portal endpoint is unreachable', function () {
    Http::fake([
        'https://milogin.michigan.gov' => Http::response('Service unavailable', 503),
    ]);

    IntegrationCredential::create([
        'key' => IntegrationCredential::KEY_CHAMPS,
        'username' => 'sshc.rpa',
        'password' => 'secret-password',
    ]);

    $payload = app(IntegrationConnectionTestService::class)
        ->testCredentialKey(IntegrationCredential::KEY_CHAMPS)
        ->toArray();

    expect($payload['success'])->toBeFalse()
        ->and($payload['status'])->toBe(GlobalIntegrationHealth::STATUS_ERROR)
        ->and(collect($payload['checks'])->firstWhere('name', 'Portal endpoint')['passed'])->toBeFalse()
        ->and($payload['recommendation'])->toContain('portal URL');
});

test('accountantsworld test passes when credentials authenticate against payroll api', function () {
    Http::fake([
        'https://dev-auth.example.com/connect/token' => Http::response([
            'access_token' => 'token',
            'expires_in' => 3600,
        ], 200),
        'https://payroll.example.com/integration/payroll/PaySchedules' => Http::response([], 200),
    ]);

    IntegrationCredential::create([
        'key' => IntegrationCredential::KEY_ACCOUNTANTSWORLD,
        'api_key' => 'aw-client-id',
        'metadata' => [
            'app_id' => 'aw-client-id',
            'api_url' => 'https://payroll.example.com/integration',
            'auth_mode' => 'oauth',
            'oauth_client_id' => 'aw-client-id',
            'oauth_client_secret' => 'aw-client-secret',
            'oauth_token_url' => 'https://dev-auth.example.com/connect/token',
        ],
    ]);

    app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

    $payload = app(IntegrationConnectionTestService::class)
        ->testCredentialKey(IntegrationCredential::KEY_ACCOUNTANTSWORLD)
        ->toArray();

    expect($payload['success'])->toBeTrue()
        ->and(collect($payload['checks'])->firstWhere('name', 'Payroll API authentication')['passed'])->toBeTrue();
});

test('accountantsworld test requires credentials for selected auth mode before probing endpoint', function () {
    config([
        'payroll.accountants_world_app_id' => '',
        'payroll.accountants_world_auth_mode' => 'api_key',
        'payroll.accountants_world_oauth_client_secret' => null,
    ]);

    $payload = app(IntegrationConnectionTestService::class)
        ->testCredentialKey(IntegrationCredential::KEY_ACCOUNTANTSWORLD)
        ->toArray();

    expect($payload['success'])->toBeFalse()
        ->and($payload['status'])->toBe(GlobalIntegrationHealth::STATUS_NOT_CONFIGURED);
});

test('sam oig test passes when exclusion endpoints are reachable including auth responses', function () {
    Http::fake([
        'https://sam.gov' => Http::response('', 403),
        'https://oig.hhs.gov/exclusions/exclusions_list.asp' => Http::response('<html></html>', 200),
    ]);

    $payload = app(IntegrationConnectionTestService::class)->testSamOig()->toArray();

    expect($payload['success'])->toBeTrue()
        ->and($payload['method'])->toBe('api_download')
        ->and(collect($payload['checks'])->pluck('name'))->toContain('SAM.gov', 'OIG LEIE');
});

test('sam oig test fails when an exclusion endpoint cannot be reached', function () {
    Http::fake([
        'https://sam.gov' => Http::response('', 200),
        'https://oig.hhs.gov/exclusions/exclusions_list.asp' => function () {
            throw new RuntimeException('Connection timed out');
        },
    ]);

    $payload = app(IntegrationConnectionTestService::class)->testSamOig()->toArray();

    expect($payload['success'])->toBeFalse()
        ->and($payload['status'])->toBe(GlobalIntegrationHealth::STATUS_ERROR)
        ->and($payload['recommendation'])->toContain('outbound HTTPS');
});

test('uhc edi test reports not configured when host is missing', function () {
    config(['billing_claims_audit.uhc_edi_host' => null]);

    $payload = app(IntegrationConnectionTestService::class)->testUhcEdi()->toArray();

    expect($payload['success'])->toBeFalse()
        ->and($payload['status'])->toBe(GlobalIntegrationHealth::STATUS_NOT_CONFIGURED)
        ->and(collect($payload['checks'])->firstWhere('name', 'EDI host configuration')['passed'])->toBeFalse();
});

test('uhc edi test passes when host is configured and reachable', function () {
    config(['billing_claims_audit.uhc_edi_host' => 'https://edi.uhc.example']);

    Http::fake([
        'https://edi.uhc.example' => Http::response('', 200),
    ]);

    $payload = app(IntegrationConnectionTestService::class)->testUhcEdi()->toArray();

    expect($payload['success'])->toBeTrue()
        ->and(collect($payload['checks'])->every(fn (array $check) => $check['passed']))->toBeTrue();
});

test('state portals aggregate reports not configured when no portal credentials exist', function () {
    $payload = app(IntegrationConnectionTestService::class)->testStatePortals()->toArray();

    expect($payload['success'])->toBeFalse()
        ->and($payload['status'])->toBe(GlobalIntegrationHealth::STATUS_NOT_CONFIGURED)
        ->and($payload['recommendation'])->toContain('Credential Vault');
});

test('state portals aggregate reports partial when only some portals are configured', function () {
    Http::fake([
        'https://milogin.michigan.gov' => Http::response('', 200),
        '*' => Http::response('', 200),
    ]);

    IntegrationCredential::create([
        'key' => IntegrationCredential::KEY_CHAMPS,
        'username' => 'sshc.rpa',
        'password' => 'secret-password',
    ]);

    $payload = app(IntegrationConnectionTestService::class)->testStatePortals()->toArray();

    expect($payload['success'])->toBeFalse()
        ->and($payload['status'])->toBe(GlobalIntegrationHealth::STATUS_PARTIAL)
        ->and($payload['message'])->toContain('portal checks passed')
        ->and($payload['summary'])->toMatch('/\d+\/\d+ checks passed/');
});

test('is credential configured reflects latest credential key test result', function () {
    IntegrationCredential::create([
        'key' => IntegrationCredential::KEY_CHAMPS,
        'username' => 'sshc.rpa',
        'password' => 'secret-password',
    ]);

    Http::fake([
        'https://milogin.michigan.gov' => Http::response('', 200),
    ]);

    expect(app(IntegrationConnectionTestService::class)->isCredentialConfigured(IntegrationCredential::KEY_CHAMPS))
        ->toBeTrue();
});
