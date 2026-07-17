<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['auth.allow_public_registration' => false]);
});

test('signup page redirects when public registration is disabled', function () {
    $this->get(route('signup'))
        ->assertRedirect(route('signin'))
        ->assertSessionHas('error');
});

test('signup post is blocked when public registration is disabled', function () {
    $this->post(route('signup.store'), [
        'fname' => 'John',
        'lname' => 'Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'captcha' => 'test',
    ])
        ->assertRedirect(route('signin'))
        ->assertSessionHas('error');

    expect(User::where('email', 'john@example.com')->exists())->toBeFalse();
});

test('signup is allowed when public registration is enabled', function () {
    config(['auth.allow_public_registration' => true]);

    $this->get(route('signup'))->assertOk();
});

test('setup account flow remains accessible', function () {
    $user = $this->createUser(User::ROLE_STAFF, [
        'invite_token' => 'valid-token',
        'invite_expires_at' => now()->addDay(),
        'password' => bcrypt('temp'),
    ]);

    $this->get(route('setup-account', [
        'email' => $user->email,
        'token' => 'valid-token',
    ]))->assertOk();
});
