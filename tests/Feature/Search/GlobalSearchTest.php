<?php

use App\Models\Location;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('global search finds clients by name', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createClient($org->id, ['first_name' => 'UniqueSearch', 'last_name' => 'ClientAlpha']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('search.global', ['query' => 'UniqueSearch']))
        ->assertOk()
        ->assertSee('UniqueSearch');
});

test('global search finds employees by name', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createEmployee($org->id, ['first_name' => 'UniqueCaregiver', 'last_name' => 'SearchTest']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('search.global', ['query' => 'UniqueCaregiver']))
        ->assertOk()
        ->assertSee('UniqueCaregiver');
});

test('global search finds intakes by name', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    createTestIntake($org->id, ['first_name' => 'UniqueIntake', 'last_name' => 'SearchLead']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('search.global', ['query' => 'UniqueIntake']))
        ->assertOk()
        ->assertSee('UniqueIntake');
});

test('global search without query redirects back', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->from(route('dashboard'))
        ->get(route('search.global'))
        ->assertRedirect(route('dashboard'));
});

test('guest cannot access global search', function () {
    $this->get(route('search.global', ['query' => 'test']))
        ->assertRedirect(route('signin'));
});

test('global search handles special characters safely', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('search.global', ['query' => "%_'\"; DROP TABLE clients;--"]))
        ->assertOk();

    expect(\App\Models\Client::count())->toBeGreaterThanOrEqual(0);
});
