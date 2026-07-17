<?php

use App\Models\IntegrationCredential;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => seedModuleBasics());

test('google workspace test connection uses unsaved draft credentials from the vault form', function () {
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'draft-access-token',
            'expires_in' => 3600,
        ]),
        'https://gmail.googleapis.com/gmail/v1/users/info%40example.com/profile' => Http::response([
            'emailAddress' => 'info@example.com',
        ]),
    ]);

    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->postJson(route('settings.global.integrations.test'), [
            'slug' => IntegrationCredential::KEY_GOOGLE_WORKSPACE,
            'draft' => [
                'username' => 'info@example.com',
                'password' => '',
                'api_key' => '',
                'metadata' => [
                    'client_id' => 'draft-client-id',
                    'client_secret' => 'draft-client-secret',
                    'refresh_token' => 'draft-refresh-token',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('google workspace vault form keeps client id and secret visible after save', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->post(route('settings.global.credential-vault'), [
            'credentials' => [[
                'key' => IntegrationCredential::KEY_GOOGLE_WORKSPACE,
                'username' => 'info@example.com',
                'metadata' => [
                    'client_id' => 'saved-client-id',
                    'client_secret' => 'saved-client-secret',
                    'refresh_token' => 'saved-refresh-token',
                ],
            ]],
        ])
        ->assertRedirect();

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.global', ['tab' => 'credential-vault', 'integration' => IntegrationCredential::KEY_GOOGLE_WORKSPACE]))
        ->assertOk()
        ->assertSee('saved-client-id', false)
        ->assertSee('saved-client-secret', false)
        ->assertSee('saved-refresh-token', false);
});
