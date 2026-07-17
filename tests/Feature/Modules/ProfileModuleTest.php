<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => seedModuleBasics());

test('guest cannot access profile', function () {
    $this->get(route('profile'))->assertRedirect(route('signin'));
    $this->put(route('profile.update'), [])->assertRedirect(route('signin'));
});

test('authenticated user can view profile page', function () {
    $user = $this->createUser(User::ROLE_ADMIN, ['name' => 'Profile Tester']);

    $this->actingAsWithTwoFactor($user)
        ->get(route('profile'))
        ->assertOk()
        ->assertSee('Profile Tester');
});

test('profile update persists name and email', function () {
    $user = $this->createUser(User::ROLE_ADMIN, [
        'name' => 'Before Name',
        'email' => 'before@example.com',
    ]);

    $this->actingAsWithTwoFactor($user)
        ->put(route('profile.update'), [
            'name' => 'After Name',
            'email' => 'after@example.com',
        ])
        ->assertRedirect(route('profile'))
        ->assertSessionHas('success');

    $fresh = $user->fresh();
    expect($fresh->name)->toBe('After Name')
        ->and($fresh->email)->toBe('after@example.com');
});

test('profile update validates required fields', function () {
    $user = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->put(route('profile.update'), ['name' => '', 'email' => ''])
        ->assertSessionHasErrors(['name', 'email']);
});

test('profile update rejects duplicate email from another user', function () {
    $org = $this->createOrganization();
    $existing = $this->createUser(User::ROLE_ADMIN, [
        'organization_id' => $org->id,
        'email' => 'taken@example.com',
    ]);
    $user = $this->createUser(User::ROLE_STAFF, [
        'organization_id' => $org->id,
        'email' => 'mine@example.com',
    ]);

    $this->actingAsWithTwoFactor($user)
        ->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $existing->email,
        ])
        ->assertSessionHasErrors(['email']);
});

test('profile update can change password when confirmed', function () {
    $user = $this->createUser(User::ROLE_ADMIN, [
        'password' => Hash::make('old-password'),
    ]);

    $this->actingAsWithTwoFactor($user)
        ->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect(route('profile'));

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});
