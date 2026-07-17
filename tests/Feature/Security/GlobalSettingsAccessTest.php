<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('super administrator can access global settings', function () {
    $user = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->get(route('settings.global'))
        ->assertOk();
});

test('administrator cannot access global settings', function () {
    $user = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->get(route('settings.global'))
        ->assertForbidden();
});

test('operations staff cannot access global settings', function () {
    $user = $this->createUser(User::ROLE_STAFF);

    $this->actingAsWithTwoFactor($user)
        ->get(route('settings.global'))
        ->assertForbidden();
});

test('employee cannot access global settings', function () {
    $user = $this->createUser(User::ROLE_EMPLOYEE);

    $this->actingAsWithTwoFactor($user)
        ->get(route('settings.global'))
        ->assertForbidden();
});
