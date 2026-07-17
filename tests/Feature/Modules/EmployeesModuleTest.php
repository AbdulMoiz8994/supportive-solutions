<?php

use App\Models\Employee;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('guest cannot access employees module', function () {
    $this->get(route('employees.index'))->assertRedirect(route('signin'));
});

test('admin can view employees index', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createEmployee($org->id, ['first_name' => 'Listed', 'last_name' => 'Employee', 'position' => 'Case Manager']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertSee('Listed');
});

test('employee store creates office staff record', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('employees.store'), employeePayload(['first_name' => 'New', 'last_name' => 'Hire']))
        ->assertRedirect(route('employees.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('employees', [
        'first_name' => 'New',
        'last_name' => 'Hire',
        'organization_id' => $org->id,
        'position' => 'Case Manager',
    ]);
});

test('employee store validates required fields and unique email', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $existing = $this->createEmployee($org->id, ['email' => 'dup@example.com']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('employees.store'), [])
        ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'position']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('employees.store'), employeePayload(['email' => 'dup@example.com']))
        ->assertSessionHasErrors(['email']);
});

test('employee update persists changes', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $employee = $this->createEmployee($org->id, [
        'first_name' => 'Before',
        'position' => 'Case Manager',
        'email' => 'before@example.com',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('employees.update', $employee->id), [
            'first_name' => 'After',
            'last_name' => $employee->last_name,
            'email' => 'before@example.com',
            'position' => 'Supervisor',
        ])
        ->assertRedirect(route('employees.index'));

    $employee->refresh();
    expect($employee->first_name)->toBe('After')
        ->and($employee->position)->toBe('Supervisor');
});

test('employee destroy removes record', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $employee = $this->createEmployee($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->delete(route('employees.destroy', $employee->id))
        ->assertRedirect(route('employees.index'));

    expect(Employee::withoutGlobalScopes()->find($employee->id))->toBeNull();
});

test('employee show returns 404 for missing record', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('employees.show', 999999))
        ->assertNotFound();
});

test('admin cannot view employee from another organization', function () {
    $orgA = $this->createOrganization();
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $employee = $this->createEmployee($orgA->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $this->actingAsWithTwoFactor($adminB)
        ->get(route('employees.show', $employee->id))
        ->assertForbidden();
});
