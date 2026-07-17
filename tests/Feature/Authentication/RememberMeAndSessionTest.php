<?php

use App\Models\User;
use App\Services\GlobalSettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('user without 2fa verification is redirected from protected routes', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $response = $this->actingAs($admin)
        ->get(route('dashboard'));

    expect($response->status())->toBeIn([302, 303])
        ->and($response->headers->get('Location'))->toContain('two-factor');
});

test('remember me sets remember token on user', function () {
    $this->mock(GlobalSettingsService::class, function ($mock) {
        $mock->shouldReceive('isTwoFactorRequired')->andReturn(false);
    });

    $user = $this->createUser(User::ROLE_ADMIN, [
        'email' => 'remember@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->post(route('signin.store'), [
        'email' => 'remember@example.com',
        'password' => 'password',
        'remember' => 'on',
    ])->assertRedirect();

    $user->refresh();
    expect($user->remember_token)->not->toBeNull();
});

test('protected routes redirect guests to signin', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('signin'));
})->with([
    'dashboard' => 'dashboard',
    'clients' => 'clients.index',
    'schedule' => 'schedule.index',
    'payroll' => 'payroll',
    'profile' => 'profile',
]);

test('guest cannot access protected routes via post', function () {
    $this->post(route('clients.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
    ])->assertRedirect(route('signin'));
});
