<?php

use App\Models\Location;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('location switch sets session to specific location', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $location = Location::create(['name' => 'Detroit Office', 'state' => 'MI']);

    $this->actingAsWithTwoFactor($admin)
        ->from(route('dashboard'))
        ->post(route('location.switch'), ['location_id' => $location->id])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success');

    expect(session('selected_location_id'))->toBe($location->id)
        ->and(session('selected_location_name'))->toBe('Detroit Office');
});

test('location switch to all clears location filter', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => 99, 'selected_location_name' => 'Old'])
        ->from(route('dashboard'))
        ->post(route('location.switch'), ['location_id' => 'all'])
        ->assertRedirect(route('dashboard'));

    expect(session('selected_location_id'))->toBeNull()
        ->and(session('selected_location_name'))->toBe('Company Wide');
});

test('location switch rejects invalid location id', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->from(route('dashboard'))
        ->post(route('location.switch'), ['location_id' => 999999])
        ->assertNotFound();
});

test('guest cannot switch location', function () {
    $this->post(route('location.switch'), ['location_id' => 1])
        ->assertRedirect(route('signin'));
});
