<?php

use App\Models\GlobalIntegrationHealth;
use App\Models\IntegrationCredential;
use App\Models\User;
use App\Services\GlobalIntegrationTestService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('is testable accepts catalog slugs and vault credential keys', function () {
    $service = app(GlobalIntegrationTestService::class);

    // D8: state portals are individual catalog rows (champs / mdhhs / sigma / ichat).
    expect($service->isTestable('ringcentral'))->toBeTrue()
        ->and($service->isTestable(IntegrationCredential::KEY_SIGMA))->toBeTrue()
        ->and($service->isTestable(IntegrationCredential::KEY_ICHAT))->toBeTrue()
        ->and($service->isTestable(IntegrationCredential::KEY_CHAMPS))->toBeTrue()
        ->and($service->isTestable('not-a-real-integration'))->toBeFalse();
});

test('test persists structured health details and latency for catalog integrations', function () {
    Http::fake([
        '*' => Http::response('ok', 200),
    ]);

    IntegrationCredential::create([
        'key' => IntegrationCredential::KEY_ACCOUNTANTSWORLD,
        'api_key' => 'vault-api-key',
        'metadata' => ['app_id' => 'vault-app-id'],
    ]);

    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    $payload = app(GlobalIntegrationTestService::class)->test('accountantsworld', $user);

    expect($payload['success'])->toBeTrue()
        ->and($payload['slug'])->toBe('accountantsworld')
        ->and($payload['status_label'])->toBe('Connected')
        ->and($payload['checks'])->not->toBeEmpty()
        ->and($payload['latency_ms'])->toBeGreaterThanOrEqual(0);

    $health = GlobalIntegrationHealth::query()->where('slug', 'accountantsworld')->first();

    expect($health)->not->toBeNull()
        ->and($health->status)->toBe(GlobalIntegrationHealth::STATUS_CONNECTED)
        ->and($health->latency_ms)->toBeGreaterThanOrEqual(0)
        ->and($health->checks())->not->toBeEmpty()
        ->and($health->last_tested_by)->toBe($user->id)
        ->and($health->details['summary'])->toContain('checks passed');
});

test('test records not configured status when credentials are missing', function () {
    $payload = app(GlobalIntegrationTestService::class)->test('ringcentral');

    expect($payload['success'])->toBeFalse()
        ->and($payload['status'])->toBe(GlobalIntegrationHealth::STATUS_NOT_CONFIGURED)
        ->and($payload['badge_class'])->toContain('slate');

    $this->assertDatabaseHas('global_integration_health', [
        'slug' => 'ringcentral',
        'status' => GlobalIntegrationHealth::STATUS_NOT_CONFIGURED,
    ]);
});

test('health index returns records keyed by slug', function () {
    GlobalIntegrationHealth::create([
        'slug' => 'availity',
        'status' => GlobalIntegrationHealth::STATUS_ERROR,
        'message' => 'OAuth failed',
        'details' => ['checks' => []],
    ]);

    $index = app(GlobalIntegrationTestService::class)->healthIndex();

    expect($index)->toHaveKey('availity')
        ->and($index['availity']->status)->toBe(GlobalIntegrationHealth::STATUS_ERROR);
});

test('sam oig catalog slug delegates to exclusion endpoint checks', function () {
    Http::fake([
        'https://sam.gov' => Http::response('', 200),
        'https://oig.hhs.gov/exclusions/exclusions_list.asp' => Http::response('<html></html>', 200),
    ]);

    $payload = app(GlobalIntegrationTestService::class)->test('sam-oig');

    expect($payload['success'])->toBeTrue()
        ->and($payload['method'])->toBe('api_download')
        ->and(collect($payload['checks'])->pluck('name'))->toContain('SAM.gov', 'OIG LEIE');

    $this->assertDatabaseHas('global_integration_health', [
        'slug' => 'sam-oig',
        'status' => GlobalIntegrationHealth::STATUS_CONNECTED,
    ]);
});
