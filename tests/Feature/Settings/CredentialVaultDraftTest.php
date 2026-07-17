<?php

use App\Models\IntegrationCredential;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => seedModuleBasics());

test('accountantsworld test connection uses unsaved draft credentials from the vault form', function () {
    Http::fake([
        'https://payroll.example.com/integration/payroll/PaySchedules' => Http::response([], 200),
    ]);

    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->postJson(route('settings.global.integrations.test'), [
            'slug' => IntegrationCredential::KEY_ACCOUNTANTSWORLD,
            'draft' => [
                'username' => '',
                'password' => '',
                'api_key' => '',
                'metadata' => [
                    'app_id' => 'draft-app-id',
                    'api_url' => 'https://payroll.example.com/integration',
                    'auth_mode' => 'api_key',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('sigma portal test connection uses unsaved draft credentials from the vault form', function () {
    Http::fake([
        'https://sigma.example.gov' => Http::response('', 200),
    ]);

    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->postJson(route('settings.global.integrations.test'), [
            'slug' => IntegrationCredential::KEY_SIGMA,
            'draft' => [
                'username' => 'sigma-user',
                'password' => 'sigma-pass',
                'api_key' => '',
                'metadata' => [
                    'portal_url' => 'https://sigma.example.gov',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});
