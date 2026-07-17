<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

test('forgot password page is accessible to guests', function () {
    $this->get(route('password.request'))
        ->assertOk();
});

test('forgot password sends reset link for valid email', function () {
    $user = $this->createUser(User::ROLE_ADMIN, ['email' => 'reset@example.com']);

    $this->post(route('password.email'), ['email' => 'reset@example.com'])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('forgot password rejects unknown email', function () {
    $this->post(route('password.email'), ['email' => 'nobody@example.com'])
        ->assertSessionHasErrors('email');
});

test('password can be reset with valid token', function () {
    $user = $this->createUser(User::ROLE_ADMIN, [
        'email' => 'reset@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'reset@example.com',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertRedirect(route('signin'));

    $user->refresh();
    expect(Hash::check('new-secure-password', $user->password))->toBeTrue();
});

test('password reset rejects invalid token', function () {
    $this->createUser(User::ROLE_ADMIN, ['email' => 'reset@example.com']);

    $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => 'reset@example.com',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertSessionHasErrors('email');
});

test('password reset requires confirmation', function () {
    $user = $this->createUser(User::ROLE_ADMIN, ['email' => 'reset@example.com']);
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'reset@example.com',
        'password' => 'new-secure-password',
        'password_confirmation' => 'mismatch',
    ])->assertSessionHasErrors('password');
});
