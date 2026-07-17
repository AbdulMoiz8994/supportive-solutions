<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('maintenance mode redirects non super administrators', function () {
    Setting::create([
        'key' => 'flags.maintenance_mode',
        'group' => 'flags',
        'value_payload' => true,
    ]);

    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('maintenance'));
});

test('super administrator can access app during maintenance mode', function () {
    Setting::create([
        'key' => 'flags.maintenance_mode',
        'group' => 'flags',
        'value_payload' => true,
    ]);

    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.global'))
        ->assertOk();
});

test('global settings apply upload max to document validation', function () {
    Setting::create([
        'key' => 'uploads.max_file_size_kb',
        'group' => 'security',
        'value_payload' => 5120,
    ]);

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $file = \Illuminate\Http\UploadedFile::fake()->create('large.pdf', 6000, 'application/pdf');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('documents.store'), [
            'documentable_type' => 'Client',
            'documentable_id' => $client->id,
            'name' => 'Too Large',
            'file' => $file,
        ])
        ->assertSessionHasErrors('file');
});

test('apply global settings middleware sets session lifetime from database', function () {
    Setting::create([
        'key' => 'security.session_timeout_minutes',
        'group' => 'security',
        'value_payload' => 45,
    ]);

    $middleware = app(\App\Http\Middleware\ApplyGlobalSettings::class);

    $middleware->handle(
        \Illuminate\Http\Request::create('/'),
        fn () => response('ok')
    );

    expect(config('session.lifetime'))->toBe(45)
        ->and(config('uploads.max_kilobytes'))->toBe(10240);
});
