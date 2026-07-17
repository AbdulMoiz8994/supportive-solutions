<?php

use App\Models\Client;
use App\Models\Intake;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('admin cannot update client from another organization', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $client = $this->createClient($orgA->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $this->actingAsWithTwoFactor($adminB)
        ->put(route('clients.update', $client->id), [
            'first_name' => 'Hacked',
            'last_name' => 'Client',
        ])
        ->assertForbidden();
});

test('operations staff without edit permission cannot delete clients', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($staff)
        ->delete(route('clients.destroy', $client->id))
        ->assertForbidden();

    expect(Client::withoutGlobalScopes()->find($client->id))->not->toBeNull();
});

test('super administrator can update client in any organization', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->put(route('clients.update', $client->id), [
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ])
        ->assertRedirect(route('clients.index'));

    expect(Client::withoutGlobalScopes()->find($client->id)->first_name)->toBe('Updated');
});

test('admin cannot convert intake from another organization', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $intake = Intake::withoutGlobalScopes()->create([
        'organization_id' => $orgA->id,
        'first_name' => 'Lead',
        'last_name' => 'Person',
        'status' => 'New',
    ]);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $this->actingAsWithTwoFactor($adminB)
        ->post(route('intakes.convert', $intake->id))
        ->assertForbidden();
});

test('employee cannot create schedules without manage schedules permission', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employeeRecord = $this->createEmployee($org->id);
    $employee = $this->createUser(User::ROLE_EMPLOYEE, [
        'organization_id' => $org->id,
    ]);

    $this->actingAsWithTwoFactor($employee)
        ->post(route('schedule.store'), [
            'client_id' => $client->id,
            'employee_id' => $employeeRecord->id,
            'date' => today()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '13:00',
        ])
        ->assertForbidden();
});

test('operations staff cannot run billing cycle', function () {
    $org = $this->createOrganization();
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($staff)
        ->post(route('billing.run'))
        ->assertNotFound();
});

test('retired billing run route returns 404 for admin', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('billing.run'))
        ->assertNotFound();
});

test('client store rejects invalid payload via form request', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.store'), [
            'first_name' => '',
            'last_name' => '',
        ])
        ->assertSessionHasErrors(['first_name', 'last_name']);
});

test('valid client store still succeeds for authorized admin', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $coverageType = \App\Models\CoverageType::query()->first()
        ?? \App\Models\CoverageType::create(['name' => 'DHS Home Help']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'coverage_type_id' => $coverageType->id,
        ])
        ->assertRedirect();

    expect(Client::withoutGlobalScopes()->where('email', 'jane@example.com')->exists())->toBeTrue();
});

test('employee cannot update another employees schedule via clock in', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $employeeUser = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $ownEmployee = $this->createEmployee($org->id, ['user_id' => $employeeUser->id]);
    $otherEmployee = $this->createEmployee($org->id, ['first_name' => 'Other']);

    $schedule = $this->createSchedule($org->id, $client->id, $otherEmployee->id, [
        'date' => today()->toDateString(),
    ]);

    $this->actingAsWithTwoFactor($employeeUser)
        ->post(route('schedule.clock-in', $schedule->id), [
            'lat' => 42.0,
            'lng' => -83.0,
        ])
        ->assertForbidden();
});

test('profile update rejects invalid email', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('profile.update'), [
            'name' => 'Admin User',
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors('email');
});
