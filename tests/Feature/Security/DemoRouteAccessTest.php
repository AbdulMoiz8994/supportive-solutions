<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['demo.routes_enabled' => false]);
});

test('super administrator can access template demo page', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->get(route('template.billing'))
        ->assertOk();
});

test('administrator cannot access template demo page', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('form-elements'))
        ->assertForbidden();
});

test('employee cannot access template demo page', function () {
    $employee = $this->createUser(User::ROLE_EMPLOYEE);

    $this->actingAsWithTwoFactor($employee)
        ->get(route('code-generator'))
        ->assertForbidden();
});

test('business routes still work for administrator', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk();
});

test('demo routes are accessible to all roles when enabled in config', function () {
    config(['demo.routes_enabled' => true]);

    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('form-elements'))
        ->assertOk();
});
