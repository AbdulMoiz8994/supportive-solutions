<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('super administrator can update global settings', function () {
    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->post(route('settings.global.update'), [
            'security' => [
                'session_timeout_minutes' => 120,
                'require_2fa' => '1',
            ],
            'uploads' => [
                'max_file_size_kb' => 10240,
            ],
            'retention' => [
                'document_retention_days' => 730,
            ],
            'billing' => [
                'default_cycle' => 'weekly',
            ],
            'flags' => [
                'maintenance_mode' => '0',
            ],
        ])
        ->assertRedirect(route('settings.global', ['tab' => 'security']))
        ->assertSessionHas('success');

    expect(Setting::where('key', 'security.session_timeout_minutes')->value('value_payload'))->toBe(120)
        ->and(Setting::where('key', 'billing.default_cycle')->value('value_payload'))->toBe('weekly');
});

test('saved global settings persist on page load', function () {
    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    Setting::create([
        'key' => 'security.session_timeout_minutes',
        'group' => 'security',
        'value_payload' => 90,
    ]);

    $this->actingAsWithTwoFactor($user)
        ->get(route('settings.global'))
        ->assertOk()
        ->assertSee('value="90"', false);
});

test('non super administrator cannot update global settings', function () {
    $user = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->post(route('settings.global.update'), [
            'security' => [
                'session_timeout_minutes' => 120,
            ],
            'uploads' => [
                'max_file_size_kb' => 10240,
            ],
            'retention' => [
                'document_retention_days' => 730,
            ],
            'billing' => [
                'default_cycle' => 'weekly',
            ],
        ])
        ->assertForbidden();
});

test('invalid global settings are rejected', function () {
    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->post(route('settings.global.update'), [
            'security' => [
                'session_timeout_minutes' => 2,
            ],
            'uploads' => [
                'max_file_size_kb' => 100,
            ],
            'retention' => [
                'document_retention_days' => 10,
            ],
            'billing' => [
                'default_cycle' => 'invalid-cycle',
            ],
        ])
        ->assertSessionHasErrors([
            'security.session_timeout_minutes',
            'uploads.max_file_size_kb',
            'retention.document_retention_days',
            'billing.default_cycle',
        ]);
});
