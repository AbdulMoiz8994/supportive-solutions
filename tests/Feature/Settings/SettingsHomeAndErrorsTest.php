<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('settings home is available at /settings for super administrators', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Settings')
        ->assertSee('Global Settings')
        ->assertSee('Integration Credentials');
});

test('legacy api keys route redirects to credential vault', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.api-keys'))
        ->assertRedirect(route('settings.global', ['tab' => 'credential-vault']));
});

test('unknown routes render branded 404 page', function () {
    $this->get('/this-route-does-not-exist')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee('Return to dashboard')
        ->assertSee('/images/logo/logo.svg');
});

test('forbidden routes render branded 403 page', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('settings.global'))
        ->assertForbidden()
        ->assertSee('Access denied')
        ->assertSee('Return to dashboard');
});
