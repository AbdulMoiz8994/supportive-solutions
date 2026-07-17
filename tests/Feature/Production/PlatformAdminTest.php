<?php

use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('super admin can create platform user with validation', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->post(route('users.store'), [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_ADMIN,
        ])
        ->assertRedirect(route('users.index'));

    expect(User::where('email', 'newadmin@example.com')->exists())->toBeTrue();
});

test('platform user creation rejects invalid payload', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->post(route('users.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ])
        ->assertSessionHasErrors(['name', 'email', 'password', 'role']);
});

test('admin cannot access platform user management', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('users.index'))
        ->assertForbidden();
});

test('cannot delete the last super administrator', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->delete(route('users.destroy', $super->id))
        ->assertForbidden();

    expect(User::find($super->id))->not->toBeNull();
});

test('cannot demote the last super administrator', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->put(route('users.update', $super->id), [
            'name' => $super->name,
            'email' => $super->email,
            'role' => User::ROLE_ADMIN,
        ])
        ->assertSessionHasErrors('role');
});

test('super admin can create and update locations', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->post(route('locations.store'), [
            'name' => 'Detroit Office',
            'state' => 'Michigan',
            'address' => '100 Main St',
            'is_active' => '1',
        ])
        ->assertRedirect(route('locations.index'));

    $location = Location::where('name', 'Detroit Office')->first();

    $this->actingAsWithTwoFactor($super)
        ->put(route('locations.update', $location->id), [
            'name' => 'Detroit Main',
            'state' => 'Michigan',
            'address' => '200 Main St',
            'is_active' => '1',
        ])
        ->assertRedirect(route('locations.index'));

    expect($location->fresh()->name)->toBe('Detroit Main');
});

test('admin cannot manage locations', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('locations.store'), [
            'name' => 'Blocked',
            'state' => 'MI',
            'address' => '1 Test',
        ])
        ->assertForbidden();
});

test('admin can access dashboard without 2fa when require_2fa is disabled', function () {
    Setting::create([
        'key' => 'security.require_2fa',
        'group' => 'security',
        'value_payload' => false,
    ]);

    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk();
});

test('admin is redirected to 2fa when require_2fa is enabled', function () {
    Setting::create([
        'key' => 'security.require_2fa',
        'group' => 'security',
        'value_payload' => true,
    ]);

    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('two-factor.choice'));
});

test('super admin still requires 2fa when global require_2fa is disabled', function () {
    Setting::create([
        'key' => 'security.require_2fa',
        'group' => 'security',
        'value_payload' => false,
    ]);

    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAs($super)
        ->get(route('settings.global'))
        ->assertRedirect(route('two-factor.choice'));
});
