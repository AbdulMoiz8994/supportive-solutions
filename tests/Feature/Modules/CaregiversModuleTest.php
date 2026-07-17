<?php

use App\Models\Employee;
use App\Models\Location;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

function caregiverStorePayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Care',
        'last_name' => 'Giver',
        'phone' => '(313) 555-0167',
        'email' => fake()->unique()->safeEmail(),
        'hourly_wage' => '15.00',
        'caregiver_type' => 'Family',
        'is_18_plus' => 1,
        'is_work_eligible' => 1,
        'has_background_check' => 0,
        'lives_with_client' => 0,
    ], $overrides);
}

test('guest cannot access caregiver routes', function () {
    $this->get(route('caregivers'))->assertRedirect(route('signin'));
    $this->post(route('caregivers.store'), caregiverStorePayload())->assertRedirect(route('signin'));
});

test('caregiver store creates employee with caregiver position', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->post(route('caregivers.store'), caregiverStorePayload(['first_name' => 'New']))
        ->assertRedirect();

    $caregiver = Employee::withoutGlobalScopes()->where('position', 'Caregiver')->latest('id')->first();
    expect($caregiver)->not->toBeNull()
        ->and($caregiver->first_name)->toBe('New')
        ->and($caregiver->location_id)->toBe($location->id);
});

test('caregiver store validates required names', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('caregivers.store'), ['first_name' => '', 'last_name' => ''])
        ->assertSessionHasErrors(['first_name', 'last_name']);
});

test('caregiver update persists wage and name changes', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $caregiver = $this->createEmployee($org->id, [
        'position' => 'Caregiver',
        'location_id' => $location->id,
        'first_name' => 'Before',
        'hourly_wage' => 12,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->post(route('caregivers.update', $caregiver->id), [
            'first_name' => 'After',
            'last_name' => 'Caregiver',
            'hourly_wage' => 18,
        ])
        ->assertRedirect();

    $fresh = $caregiver->fresh();
    expect($fresh->first_name)->toBe('After')
        ->and((float) $fresh->hourly_wage)->toBe(18.0);
});

test('caregiver assignment store links client', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver', 'location_id' => $location->id]);
    $client = $this->createClient($org->id, ['location_id' => $location->id]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->post(route('caregivers.assignments.store', $caregiver->id), [
            'client_id' => $client->id,
            'relationship' => 'Son',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('caregiver_assignments', [
        'employee_id' => $caregiver->id,
        'client_id' => $client->id,
    ]);
});

test('caregiver note store creates note record', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('caregivers.notes.store', $caregiver->id), [
            'body' => 'Onboarding complete',
            'tag' => 'Activity',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('caregiver_notes', [
        'employee_id' => $caregiver->id,
        'body' => 'Onboarding complete',
    ]);
});

test('caregiver show 404 for missing id', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('caregivers.show', 999999))
        ->assertNotFound();
});

test('caregiver schedule tab lists visits when assigned', function () {
    $org = $this->createOrganization();
    $location = Location::create(['name' => 'Detroit', 'state' => 'MI']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['location_id' => $location->id]);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver', 'location_id' => $location->id]);
    $this->createSchedule($org->id, $client->id, $caregiver->id, [
        'title' => 'CG Module Visit',
        'location_id' => $location->id,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->get(route('caregivers.show', ['id' => $caregiver->id, 'tab' => 'schedule']))
        ->assertOk()
        ->assertSee('CG Module Visit');
});
