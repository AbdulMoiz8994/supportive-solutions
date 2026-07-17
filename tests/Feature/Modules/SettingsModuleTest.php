<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('super admin can access settings home', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Settings')
        ->assertSee('Global Settings')
        ->assertSee('Integration Credentials');
});

test('admin can access settings home', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Settings')
        ->assertSee('Staff & AI Agents');
});

test('admin cannot access super admin global settings page', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('settings.global'))
        ->assertForbidden();
});

test('super admin can view global settings page', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.global'))
        ->assertOk();
});

test('super admin can update global security settings', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->post(route('settings.global.update'), [
            'security' => ['session_timeout_minutes' => 90, 'require_2fa' => '1'],
            'uploads' => ['max_file_size_kb' => 10240],
            'retention' => ['document_retention_days' => 730],
            'billing' => ['default_cycle' => 'monthly'],
            'flags' => ['maintenance_mode' => '0'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Setting::where('key', 'security.session_timeout_minutes')->value('value_payload'))->toBe(90);
});

test('global settings update rejects invalid values', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->post(route('settings.global.update'), [
            'security' => ['session_timeout_minutes' => 2],
            'uploads' => ['max_file_size_kb' => 100],
            'retention' => ['document_retention_days' => 10],
            'billing' => ['default_cycle' => 'invalid-cycle'],
        ])
        ->assertSessionHasErrors([
            'security.session_timeout_minutes',
            'uploads.max_file_size_kb',
            'retention.document_retention_days',
            'billing.default_cycle',
        ]);
});

test('super admin can update credential vault entries', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->post(route('settings.global.credential-vault'), [
            'credentials' => [
                [
                    'key' => \App\Models\IntegrationCredential::KEY_RINGCENTRAL,
                    'metadata' => [
                        'server_url' => 'https://platform.ringcentral.com',
                        'client_id' => 'rc-client-id',
                        'client_secret' => 'rc-secret',
                        'jwt' => 'eyJ.test.jwt',
                        'from_number' => '+15550001111',
                    ],
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $summary = app(\App\Services\CredentialVaultService::class)->summaryForView()[\App\Models\IntegrationCredential::KEY_RINGCENTRAL];

    expect($summary['metadata']['client_id'])->toBe('rc-client-id')
        ->and($summary['metadata']['client_secret'])->toBe('rc-secret')
        ->and($summary['metadata']['jwt'])->toBe('eyJ.test.jwt')
        ->and($summary['configured'])->toBeTrue();
});

test('ringcentral credential vault requires jwt', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->post(route('settings.global.credential-vault'), [
            'credentials' => [
                [
                    'key' => \App\Models\IntegrationCredential::KEY_RINGCENTRAL,
                    'metadata' => [
                        'client_id' => 'rc-client-id',
                        'client_secret' => 'rc-secret',
                    ],
                ],
            ],
        ])
        ->assertSessionHasErrors(['credentials.0.metadata.jwt']);
});

test('settings api keys route redirects to credential vault', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.api-keys'))
        ->assertRedirect(route('settings.global', ['tab' => 'credential-vault']));
});

test('settings roles page loads for super admin', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.roles'))
        ->assertOk();
});
