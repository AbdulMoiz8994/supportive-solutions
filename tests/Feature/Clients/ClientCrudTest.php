<?php

use App\Models\Client;
use App\Models\CaregiverAssignment;
use App\Models\CoverageType;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function clientPayload(array $overrides = []): array
{
    $coverageType = CoverageType::query()->first() ?? CoverageType::create([
        'name' => 'DHS Home Help',
    ]);

    return array_merge([
        'first_name' => 'Johnny',
        'last_name' => 'Appleseed',
        'phone' => '(313) 555-0101',
        'address' => '500 Client Ave',
        'county' => 'Wayne',
        'coverage_type_id' => $coverageType->id,
    ], $overrides);
}

test('guest cannot access client pages', function () {
    $this->get(route('clients.index'))->assertRedirect(route('signin'));
    $this->post(route('clients.store'), clientPayload())->assertRedirect(route('signin'));
});

test('employee without permission cannot view the client registry', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($employee)
        ->get(route('clients.index'))
        ->assertForbidden();
});

test('admin can view the client registry', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createClient($org->id, ['first_name' => 'Visible', 'last_name' => 'Client']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Visible');
});

test('client registry separates intake and enrolment entry points', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee(route('intakes.index'), false)
        ->assertSee(route('clients.create'), false)
        ->assertSee('New intake')
        ->assertSee('Enrol client')
        ->assertDontSee('New Client / Intake');
});

test('client store validates required name fields', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.store'), ['first_name' => '', 'last_name' => ''])
        ->assertSessionHasErrors(['first_name', 'last_name']);
});

/**
 * REGRESSION — parallel to the caregiver 404. A client created with a location
 * selected previously had location_id NULL, so the LocationScope hid it from the
 * registry list (show still worked via withoutGlobalScopes, so no 404 — just a
 * silently missing record).
 */
test('creating a client under a selected location keeps it visible in the registry', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $response = $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->post(route('clients.store'), clientPayload());

    $client = Client::withoutGlobalScopes()->latest('id')->first();

    expect($client)->not->toBeNull()
        ->and($client->location_id)->toBe($location->id);

    $response->assertRedirect(route('clients.show', $client->id));

    // The new client must show in the registry under the active location.
    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Johnny');
});

test('admin can view a client profile', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['location_id' => $location->id, 'first_name' => 'Profile', 'last_name' => 'View']);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->get(route('clients.show', $client->id))
        ->assertOk()
        ->assertSee('Profile');
});

test('admin can update a client inline and return to the tab', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['location_id' => $location->id, 'first_name' => 'Before', 'last_name' => 'Edit']);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->put(route('clients.update', $client->id), [
            'first_name' => 'After',
            'last_name' => 'Edit',
            'tab' => 'demographics',
        ])
        ->assertRedirect(route('clients.show', ['id' => $client->id, 'tab' => 'demographics']));

    expect($client->fresh()->first_name)->toBe('After');
});

test('admin can persist demographics dropdown edits across reload', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $coverageType = CoverageType::create(['name' => 'MICH Waiver']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, [
        'location_id' => $location->id,
        'first_name' => 'Persist',
        'last_name' => 'Dropdown',
        'gender' => 'Male',
        'preferred_language' => 'English',
        'coverage_type_id' => $coverageType->id,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->put(route('clients.update', $client->id), [
            'gender' => 'Female',
            'preferred_language' => 'Arabic',
            'tab' => 'demographics',
        ])
        ->assertRedirect(route('clients.show', ['id' => $client->id, 'tab' => 'demographics']));

    $client->refresh();
    expect($client->gender)->toBe('Female')
        ->and($client->preferred_language)->toBe('Arabic');

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->get(route('clients.show', ['id' => $client->id, 'tab' => 'demographics']))
        ->assertOk()
        ->assertSee('Female')
        ->assertSee('Arabic');
});

test('a bare client update (no tab) returns to the registry', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Bare', 'last_name' => 'Update']);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), ['first_name' => 'Bared'])
        ->assertRedirect(route('clients.index'));

    expect($client->fresh()->first_name)->toBe('Bared');
});

test('client store returns 422 json for blank required fields', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('clients.store'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'last_name', 'coverage_type_id']);
});

test('admin can add a care detail to a client', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Care', 'last_name' => 'Plan']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.care-details.store', $client->id), [
            'billing_code' => 'T1019',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_units' => 320,
            'authorized_by' => 'Dr. Smith',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('care_details', [
        'client_id' => $client->id,
        'billing_code' => 'T1019',
    ]);
});

test('admin can assign caregiver and client profile reflects assignment', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Assign', 'last_name' => 'Client']);
    $caregiver = $this->createEmployee($org->id, [
        'first_name' => 'First',
        'last_name' => 'Caregiver',
        'position' => 'Caregiver',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.assign-caregiver', $client->id), [
            'employee_id' => $caregiver->id,
            'relationship' => 'Friend',
            'live_in' => '1',
        ])
        ->assertRedirect(route('clients.show', ['id' => $client->id, 'tab' => 'caregiver']));

    $this->assertDatabaseHas('caregiver_assignments', [
        'client_id' => $client->id,
        'employee_id' => $caregiver->id,
        'status' => 'Active',
        'relationship' => 'Friend',
        'live_in' => 1,
    ]);

    $response = $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.show', ['id' => $client->id, 'tab' => 'caregiver']));

    $response->assertOk()
        ->assertSee('Caregiver')
        ->assertSee('First Caregiver');
});

test('reassign caregiver ends previous assignment and shows new caregiver', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Reassign', 'last_name' => 'Client']);
    $firstCaregiver = $this->createEmployee($org->id, [
        'first_name' => 'Alpha',
        'last_name' => 'Caregiver',
        'position' => 'Caregiver',
    ]);
    $secondCaregiver = $this->createEmployee($org->id, [
        'first_name' => 'Beta',
        'last_name' => 'Caregiver',
        'position' => 'Caregiver',
    ]);

    CaregiverAssignment::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'employee_id' => $firstCaregiver->id,
        'status' => 'Active',
        'assigned_since' => now()->subDays(3)->toDateString(),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.assign-caregiver', $client->id), [
            'employee_id' => $secondCaregiver->id,
            'relationship' => 'Professional',
            'live_in' => '0',
        ])
        ->assertRedirect(route('clients.show', ['id' => $client->id, 'tab' => 'caregiver']));

    expect(CaregiverAssignment::where('client_id', $client->id)->where('status', 'Active')->count())->toBe(1)
        ->and(CaregiverAssignment::where('client_id', $client->id)->where('employee_id', $firstCaregiver->id)->where('status', 'Ended')->exists())->toBeTrue()
        ->and(CaregiverAssignment::where('client_id', $client->id)->where('employee_id', $secondCaregiver->id)->where('status', 'Active')->exists())->toBeTrue();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.show', ['id' => $client->id, 'tab' => 'caregiver']))
        ->assertOk()
        ->assertSee('Beta Caregiver');
});

test('admin can delete a client', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Doomed', 'last_name' => 'Client']);

    $this->actingAsWithTwoFactor($admin)
        ->delete(route('clients.destroy', $client->id))
        ->assertRedirect(route('clients.index'));

    expect(Client::withoutGlobalScopes()->find($client->id))->toBeNull();
});

test('a user cannot delete a client from another organization', function () {
    $orgA = $this->createOrganization();
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);
    $client = $this->createClient($orgA->id, ['first_name' => 'Protected', 'last_name' => 'Client']);

    $this->actingAsWithTwoFactor($adminB)
        ->delete(route('clients.destroy', $client->id))
        ->assertForbidden();

    expect(Client::withoutGlobalScopes()->find($client->id))->not->toBeNull();
});
