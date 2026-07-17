<?php

use App\Models\GlobalIntegrationHealth;
use App\Models\IntegrationCredential;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\CredentialVaultService;
use App\Services\GlobalSettingsPreserveService;
use App\Services\GlobalSettingsService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::disk('local')->delete(GlobalSettingsPreserveService::BACKUP_PATH);
    Storage::disk('local')->delete(GlobalSettingsPreserveService::LEGACY_CREDENTIAL_VAULT_BACKUP_PATH);
});

test('global settings preserve roundtrips credential vault entries', function () {
    app(CredentialVaultService::class)->upsert(IntegrationCredential::KEY_CHAMPS, [
        'username' => 'champs@example.com',
        'password' => 'vault-secret',
    ]);

    app(CredentialVaultService::class)->upsert(IntegrationCredential::KEY_AVAILITY, [
        'username' => 'availity-user',
        'api_key' => 'demo-key-value',
        'password' => 'demo-secret-value',
        'metadata' => [
            'env' => 'demo',
            'default_payer_id' => 'PAYER123',
        ],
    ]);

    app(GlobalSettingsPreserveService::class)->backup();

    IntegrationCredential::query()->delete();

    expect(IntegrationCredential::count())->toBe(0);

    app(GlobalSettingsPreserveService::class)->restore();

    expect(IntegrationCredential::count())->toBe(2)
        ->and(app(CredentialVaultService::class)->username(IntegrationCredential::KEY_CHAMPS))->toBe('champs@example.com')
        ->and(app(CredentialVaultService::class)->username(IntegrationCredential::KEY_AVAILITY))->toBe('availity-user')
        ->and(app(CredentialVaultService::class)->apiKey(IntegrationCredential::KEY_AVAILITY))->toBe('demo-key-value')
        ->and(app(CredentialVaultService::class)->metadata(IntegrationCredential::KEY_AVAILITY)['default_payer_id'])->toBe('PAYER123');
});

test('global settings preserve roundtrips settings and agency profile', function () {
    $this->seed(\Database\Seeders\OrganizationSeeder::class);

    $organization = Organization::query()->orderBy('id')->first();

    expect($organization)->not->toBeNull();

    app(GlobalSettingsService::class)->update([
        'security.session_timeout_minutes' => 120,
        'programs.mich_hourly_rate' => 32.5,
        'billing.default_asw_email' => 'asw@example.gov',
    ]);

    $organization->update([
        'main_phone' => '(313) 555-0100',
        'efax_number' => '(313) 555-0101',
        'legal_business_name' => 'Custom Agency Legal Name LLC',
    ]);

    GlobalIntegrationHealth::query()->create([
        'slug' => IntegrationCredential::KEY_AVAILITY,
        'status' => GlobalIntegrationHealth::STATUS_CONNECTED,
        'message' => 'Connected',
        'latency_ms' => 42,
        'details' => ['checks' => [['name' => 'Token', 'passed' => true, 'detail' => 'OK']]],
        'last_tested_at' => now(),
    ]);

    app(GlobalSettingsPreserveService::class)->backup();

    Setting::query()->delete();
    GlobalIntegrationHealth::query()->delete();
    $organization->update([
        'main_phone' => null,
        'efax_number' => null,
        'legal_business_name' => 'Seeded Default Name',
    ]);

    app(GlobalSettingsPreserveService::class)->restore();

    expect(app(GlobalSettingsService::class)->get('security.session_timeout_minutes'))->toBe(120)
        ->and(app(GlobalSettingsService::class)->get('programs.mich_hourly_rate'))->toBe(32.5)
        ->and(app(GlobalSettingsService::class)->get('billing.default_asw_email'))->toBe('asw@example.gov')
        ->and($organization->fresh()->main_phone)->toBe('(313) 555-0100')
        ->and($organization->fresh()->efax_number)->toBe('(313) 555-0101')
        ->and($organization->fresh()->legal_business_name)->toBe('Custom Agency Legal Name LLC')
        ->and(GlobalIntegrationHealth::where('slug', IntegrationCredential::KEY_AVAILITY)->value('status'))
        ->toBe(GlobalIntegrationHealth::STATUS_CONNECTED);
});

test('global settings preserve restore is a no-op when backup is empty', function () {
    app(GlobalSettingsPreserveService::class)->backup();

    IntegrationCredential::query()->delete();
    Setting::query()->delete();

    app(GlobalSettingsPreserveService::class)->restore();

    expect(IntegrationCredential::count())->toBe(0)
        ->and(Setting::count())->toBe(0);
});

test('global settings preserve restores legacy credential vault backup format', function () {
    app(CredentialVaultService::class)->upsert(IntegrationCredential::KEY_CHAMPS, [
        'username' => 'legacy@example.com',
        'password' => 'legacy-secret',
    ]);

    $raw = \Illuminate\Support\Facades\DB::table('integration_credentials')
        ->where('key', IntegrationCredential::KEY_CHAMPS)
        ->first();

    Storage::disk('local')->put(
        GlobalSettingsPreserveService::LEGACY_CREDENTIAL_VAULT_BACKUP_PATH,
        json_encode([
            'rows' => [[
                'key' => $raw->key,
                'username' => $raw->username,
                'password' => $raw->password,
                'api_key' => $raw->api_key,
                'metadata' => $raw->metadata,
                'created_at' => $raw->created_at,
                'updated_at' => $raw->updated_at,
            ]],
        ], JSON_THROW_ON_ERROR)
    );

    IntegrationCredential::query()->delete();

    app(GlobalSettingsPreserveService::class)->restore();

    expect(app(CredentialVaultService::class)->username(IntegrationCredential::KEY_CHAMPS))->toBe('legacy@example.com');
});
