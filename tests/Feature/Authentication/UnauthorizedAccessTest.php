<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(fn () => seedModuleBasics());

test('employee role cannot access admin-only modules', function (string $routeName) {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($employee)
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    'clients index' => 'clients.index',
    'caregivers' => 'caregivers',
    'intakes' => 'intakes.index',
    'billing claims audit' => 'billing-claims-audit.index',
    'payroll' => 'payroll',
    'settings' => 'settings.index',
    'staff' => 'staff.index',
]);

test('employee can access permitted modules', function (string $routeName) {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($employee)
        ->get(route($routeName))
        ->assertSuccessful();
})->with([
    'schedule' => 'schedule.index',
    'messages' => 'messages.index',
    'profile' => 'profile',
]);

test('operations staff cannot access super admin routes', function () {
    $staff = $this->createUser(User::ROLE_STAFF);

    $this->actingAsWithTwoFactor($staff)
        ->get(route('settings.global'))
        ->assertForbidden();

    $this->actingAsWithTwoFactor($staff)
        ->get('/users')
        ->assertForbidden();
});

test('admin cannot access super admin global settings', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('settings.global'))
        ->assertForbidden();
});

test('super admin can access platform management routes', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->get(route('settings.global'))
        ->assertOk();

    $this->actingAsWithTwoFactor($super)
        ->get('/users')
        ->assertOk();
});
