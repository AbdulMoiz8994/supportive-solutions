<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('caregiver profile Personal fields persist via caregivers.update', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $cg = $this->createEmployee($org->id, ['position' => 'Caregiver', 'first_name' => 'Old', 'last_name' => 'Name']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('caregivers.update', $cg->id), [
            'first_name' => 'New',
            'last_name' => 'Caregiver',
            'phone' => '(313) 555-0199',
            'hourly_wage' => 16.75,
            'preferred_language' => 'Arabic',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $cg->refresh();
    expect($cg->first_name)->toBe('New');
    expect($cg->phone)->toBe('(313) 555-0199');
    expect((float) $cg->hourly_wage)->toBe(16.75);
    expect($cg->preferred_language)->toBe('Arabic');
});

test('caregiver Emergency Contact (incl. email) now persists', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $cg = $this->createEmployee($org->id, ['position' => 'Caregiver']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('caregivers.update', $cg->id), [
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_relationship' => 'Sister',
            'emergency_contact_phone' => '(248) 555-0100',
            'emergency_contact_email' => 'jane@example.com',
        ])->assertRedirect();

    $cg->refresh();
    expect($cg->emergency_contact_name)->toBe('Jane Doe');
    expect($cg->emergency_contact_relationship)->toBe('Sister');
    expect($cg->emergency_contact_phone)->toBe('(248) 555-0100');
    expect($cg->emergency_contact_email)->toBe('jane@example.com');
});

test('client Compliance tab HHAeXchange Verification persists evv_status', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), [
            'section' => 'comp-evv',
            'tab' => 'compliance',
            'evv_status' => 'Active — clock-in / out required',
        ])
        ->assertRedirect(route('clients.show', ['id' => $client->id, 'tab' => 'compliance']))
        ->assertSessionHas('success', 'Changes saved.');

    expect($client->fresh()->evv_status)->toBe('Active — clock-in / out required');
});
