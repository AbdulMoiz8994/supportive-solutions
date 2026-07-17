<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\GlobalSettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('signin page is accessible to guests', function () {
    $this->get(route('signin'))
        ->assertOk()
        ->assertSee('Sign In', false);
});

test('valid login creates session and logs activity', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, [
        'organization_id' => $org->id,
        'email' => 'login@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->mock(GlobalSettingsService::class, function ($mock) {
        $mock->shouldReceive('isTwoFactorRequired')->andReturn(false);
    });

    $this->post(route('signin.store'), [
        'email' => 'login@example.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($admin);

    expect(ActivityLog::where('user_id', $admin->id)->where('action', 'User Login')->exists())->toBeTrue();
});

test('invalid login returns validation error without authenticating', function () {
    $this->createUser(User::ROLE_ADMIN, [
        'email' => 'real@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->post(route('signin.store'), [
        'email' => 'real@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('inactive users cannot log in', function () {
    $this->createUser(User::ROLE_ADMIN, [
        'email' => 'inactive@example.com',
        'password' => Hash::make('password'),
        'is_active' => false,
    ]);

    $this->post(route('signin.store'), [
        'email' => 'inactive@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('logout invalidates session and redirects home', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

test('authenticated user visiting signin is not forced to logout', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('signin'))
        ->assertOk();
});

test('super admin login redirects to two factor choice', function () {
    $org = $this->createOrganization();
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, [
        'organization_id' => $org->id,
        'email' => 'super@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->post(route('signin.store'), [
        'email' => 'super@example.com',
        'password' => 'password',
    ])->assertRedirect(route('two-factor.choice'));

    $this->assertAuthenticatedAs($super);
});

test('the enforced=false master switch disables 2FA even for a super admin', function () {
    config()->set('two_factor.enforced', false);

    $org = $this->createOrganization();
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, [
        'organization_id' => $org->id,
        'email' => 'super2@example.com',
        'password' => Hash::make('password'),
    ]);

    // Simple login — straight to the app, no two-factor hop.
    $this->post(route('signin.store'), [
        'email' => 'super2@example.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    // And an authed request is not bounced to the 2FA screen by the middleware.
    $this->get(route('dashboard'))->assertOk();
});

test('an exempt email skips 2FA while it stays enforced for everyone else', function () {
    config()->set('two_factor.enforced', true);
    config()->set('two_factor.exempt_emails', ['tester@example.com']);

    $this->mock(GlobalSettingsService::class, function ($mock) {
        $mock->shouldReceive('isTwoFactorRequired')->andReturn(true);
    });

    $exempt = $this->createUser(User::ROLE_ADMIN, [
        'email' => 'tester@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->post(route('signin.store'), [
        'email' => 'tester@example.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($exempt);
});

test('a non-exempt user is still sent to 2FA while enforced', function () {
    config()->set('two_factor.enforced', true);
    config()->set('two_factor.exempt_emails', ['tester@example.com']);

    $this->mock(GlobalSettingsService::class, function ($mock) {
        $mock->shouldReceive('isTwoFactorRequired')->andReturn(true);
    });

    $this->createUser(User::ROLE_ADMIN, [
        'email' => 'notexempt@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->post(route('signin.store'), [
        'email' => 'notexempt@example.com',
        'password' => 'password',
    ])->assertRedirect(route('two-factor.choice'));
});
