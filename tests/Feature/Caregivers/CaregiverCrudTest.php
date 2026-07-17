<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function caregiverPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Garcia',
        'phone' => '(313) 555-0167',
        'address' => '123 Care St',
        'email' => 'maria.garcia@example.com',
        'hourly_wage' => '15.00',
        'caregiver_type' => 'Family',
        'is_18_plus' => 1,
        'is_work_eligible' => 1,
        'has_background_check' => 0,
        'lives_with_client' => 0,
    ], $overrides);
}

test('guest cannot access caregiver pages', function () {
    $this->get(route('caregivers'))->assertRedirect(route('signin'));
    $this->get(route('caregivers.create'))->assertRedirect(route('signin'));
    $this->post(route('caregivers.store'), caregiverPayload())->assertRedirect(route('signin'));
});

test('admin can view the caregiver registry', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('caregivers'))
        ->assertOk()
        ->assertSee('Caregiver');
});

test('admin can view the new caregiver onboarding form', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('caregivers.create'))
        ->assertOk();
});

test('caregiver store validates required name fields', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('caregivers.store'), ['first_name' => '', 'last_name' => ''])
        ->assertSessionHasErrors(['first_name', 'last_name']);
});

/**
 * REGRESSION — the reported "creates a record but then 404s" bug.
 *
 * With a location selected, store() previously left location_id NULL, so the
 * redirect to caregivers.show hit the LocationScope filter and findOrFail 404'd.
 */
test('creating a caregiver under a selected location does not 404 and stays visible', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $response = $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->post(route('caregivers.store'), caregiverPayload());

    $caregiver = Employee::withoutGlobalScopes()->where('position', 'Caregiver')->latest('id')->first();

    expect($caregiver)->not->toBeNull()
        ->and($caregiver->location_id)->toBe($location->id);

    $response->assertRedirect(route('caregivers.show', $caregiver->id));

    // Following the redirect must resolve — this is exactly where the 404 happened.
    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->get(route('caregivers.show', $caregiver->id))
        ->assertOk()
        ->assertSee('Maria');

    // And the new caregiver appears in the registry under that location.
    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->get(route('caregivers'))
        ->assertOk()
        ->assertSee('Maria');
});

/**
 * REGRESSION — workflow queue links to caregivers in other locations (or with null location_id).
 */
test('caregiver show opens when id belongs to another location than the session filter', function () {
    $org = $this->createOrganization();
    $detroit = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $lansing = Location::create(['name' => 'Lansing', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $caregiver = $this->createEmployee($org->id, [
        'position' => 'Caregiver',
        'location_id' => $lansing->id,
        'first_name' => 'Cross',
        'last_name' => 'Location',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $detroit->id])
        ->get(route('caregivers.show', ['id' => $caregiver->id, 'tab' => 'checks']))
        ->assertOk()
        ->assertSee('Cross Location');
});

test('creating a caregiver in Company Wide context (no location) still works', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $response = $this->actingAsWithTwoFactor($admin)
        ->post(route('caregivers.store'), caregiverPayload(['first_name' => 'NoLoc']));

    $caregiver = Employee::withoutGlobalScopes()->where('position', 'Caregiver')->latest('id')->first();

    $response->assertRedirect(route('caregivers.show', $caregiver->id));

    $this->actingAsWithTwoFactor($admin)
        ->get(route('caregivers.show', $caregiver->id))
        ->assertOk();
});

test('creating a caregiver with background-check consent kicks off the four checks', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->post(route('caregivers.store'), caregiverPayload(['has_background_check' => 1]))
        ->assertRedirect();

    $caregiver = Employee::withoutGlobalScopes()->where('position', 'Caregiver')->latest('id')->first();

    $this->assertDatabaseCount('background_checks', 4);
    expect($caregiver->backgroundChecks()->count())->toBe(4);
});

test('creating a caregiver with an assigned client links them', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['location_id' => $location->id, 'first_name' => 'Bound', 'last_name' => 'Client']);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->post(route('caregivers.store'), caregiverPayload(['client_id' => $client->id]))
        ->assertRedirect();

    $caregiver = Employee::withoutGlobalScopes()->where('position', 'Caregiver')->latest('id')->first();

    $this->assertDatabaseHas('caregiver_assignments', [
        'employee_id' => $caregiver->id,
        'client_id' => $client->id,
    ]);
});

test('admin can update a caregiver inline', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $caregiver = $this->createEmployee($org->id, [
        'position' => 'Caregiver',
        'location_id' => $location->id,
        'first_name' => 'Old',
        'last_name' => 'Name',
        'hourly_wage' => 12,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->post(route('caregivers.update', $caregiver->id), [
            'first_name' => 'NewName',
            'last_name' => 'Updated',
            'hourly_wage' => 20,
        ])
        ->assertRedirect();

    $fresh = $caregiver->fresh();
    expect($fresh->first_name)->toBe('NewName')
        ->and((float) $fresh->hourly_wage)->toBe(20.0);
});

test('admin can add a note to a caregiver', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver', 'location_id' => $location->id]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->post(route('caregivers.notes.store', $caregiver->id), ['body' => 'Follow-up scheduled', 'tag' => 'Activity'])
        ->assertRedirect(route('caregivers.show', $caregiver->id));

    $this->assertDatabaseHas('caregiver_notes', [
        'employee_id' => $caregiver->id,
        'body' => 'Follow-up scheduled',
    ]);
});

test('admin can add a second client assignment to a caregiver', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver', 'location_id' => $location->id]);
    $client = $this->createClient($org->id, ['location_id' => $location->id]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->post(route('caregivers.assignments.store', $caregiver->id), [
            'client_id' => $client->id,
            'relationship' => 'Daughter',
            'scheduled_hours' => 20,
        ])
        ->assertRedirect(route('caregivers.show', $caregiver->id));

    $this->assertDatabaseHas('caregiver_assignments', [
        'employee_id' => $caregiver->id,
        'client_id' => $client->id,
        'relationship' => 'Daughter',
    ]);
});

test('caregiver show 404s for a non-existent id', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('caregivers.show', 999999))
        ->assertNotFound();
});
