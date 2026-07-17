<?php

use App\Models\User;

beforeEach(fn () => seedModuleBasics());

/**
 * Permission-gated routes: verify role access matrix.
 */
dataset('permission gated routes', [
    'view clients' => ['clients.index', User::ROLE_ADMIN, 200, User::ROLE_EMPLOYEE, 403],
    'view payroll' => ['payroll', User::ROLE_ADMIN, 200, User::ROLE_EMPLOYEE, 403],
    'view billing claims' => ['billing-claims-audit.index', User::ROLE_ADMIN, 200, User::ROLE_EMPLOYEE, 403],
    'view schedule' => ['schedule.index', User::ROLE_ADMIN, 200, User::ROLE_EMPLOYEE, 200],
    'view messages' => ['messages.index', User::ROLE_ADMIN, 200, User::ROLE_EMPLOYEE, 200],
    'view dashboard' => ['dashboard', User::ROLE_ADMIN, 200, User::ROLE_EMPLOYEE, 403],
    'view reports' => ['reports.index', User::ROLE_ADMIN, 200, User::ROLE_EMPLOYEE, 403],
    'view communications' => ['communications.index', User::ROLE_ADMIN, 200, User::ROLE_EMPLOYEE, 403],
]);

test('permission gated routes enforce role access', function (
    string $routeName,
    string $allowedRole,
    int $allowedStatus,
    string $deniedRole,
    int $deniedStatus
) {
    $org = $this->createOrganization();
    $allowed = $this->createUser($allowedRole, ['organization_id' => $org->id]);
    $denied = $this->createUser($deniedRole, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($allowed)
        ->get(route($routeName))
        ->assertStatus($allowedStatus);

    $this->actingAsWithTwoFactor($denied)
        ->get(route($routeName))
        ->assertStatus($deniedStatus);
})->with('permission gated routes');

test('operations staff can view clients but not delete', function () {
    $org = $this->createOrganization();
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $this->actingAsWithTwoFactor($staff)
        ->get(route('clients.index'))
        ->assertOk();

    $this->actingAsWithTwoFactor($staff)
        ->delete(route('clients.destroy', $client->id))
        ->assertForbidden();
});

test('admin can create clients operations staff can view', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);
    $coverageType = \App\Models\CoverageType::query()->first()
        ?? \App\Models\CoverageType::create(['name' => 'DHS Home Help']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.store'), [
            'first_name' => 'Perm',
            'last_name' => 'Test',
            'email' => 'perm@example.com',
            'coverage_type_id' => $coverageType->id,
        ])
        ->assertRedirect();

    $this->actingAsWithTwoFactor($staff)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Perm');
});

test('super admin bypasses all permission checks', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    foreach (['dashboard', 'clients.index', 'payroll', 'settings.global', 'billing-claims-audit.index'] as $route) {
        $this->actingAsWithTwoFactor($super)
            ->get(route($route))
            ->assertSuccessful();
    }
});
