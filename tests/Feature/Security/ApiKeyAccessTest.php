<?php

use App\Models\ApiKey;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('super administrator can access api keys index', function () {
    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->get(route('api-keys'))
        ->assertOk();
});

test('administrator cannot access api keys index', function () {
    $user = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->get(route('api-keys'))
        ->assertForbidden();
});

test('employee cannot create api keys', function () {
    $user = $this->createUser(User::ROLE_EMPLOYEE);

    $this->actingAsWithTwoFactor($user)
        ->postJson(route('api-keys.store'), ['name' => 'Test Key'])
        ->assertForbidden();
});

test('super administrator can create api keys', function () {
    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->postJson(route('api-keys.store'), ['name' => 'Production Key'])
        ->assertOk()
        ->assertJsonPath('apiKey.name', 'Production Key');

    expect(ApiKey::where('name', 'Production Key')->exists())->toBeTrue();
});

test('administrator cannot delete api keys', function () {
    $user = $this->createUser(User::ROLE_ADMIN);
    $apiKey = ApiKey::create([
        'name' => 'Existing Key',
        'key' => 'api_testkey',
        'active' => true,
    ]);

    $this->actingAsWithTwoFactor($user)
        ->deleteJson(route('api-keys.destroy', $apiKey->id))
        ->assertForbidden();
});
