<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('the Referral panel persists its fields and returns to the intake tab', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), [
            'section' => 'intake-referral',
            'tab' => 'intake',
            'referral_source' => 'Doctor referral',
            'referral_received_date' => '2026-07-01',
            'referred_by' => 'Dr. Alice Nguyen',
            'currently_receiving_care' => 'No',
            'intake_taken_by' => 'Jordan Office',
            'intake_date' => '2026-07-02',
        ])
        ->assertRedirect(route('clients.show', ['id' => $client->id, 'tab' => 'intake']))
        ->assertSessionHas('success', 'Changes saved.');

    $client->refresh();
    expect($client->referral_source)->toBe('Doctor referral');
    expect($client->referred_by)->toBe('Dr. Alice Nguyen');
    expect($client->intake_taken_by)->toBe('Jordan Office');
    expect($client->referral_received_date?->toDateString())->toBe('2026-07-01');
    expect($client->intake_date?->toDateString())->toBe('2026-07-02');
});

test('the Eligibility Screening panel persists date and result', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), [
            'section' => 'intake-screening',
            'tab' => 'intake',
            'eligibility_verified_date' => '2026-06-15',
            'eligibility_result' => 'Eligible',
        ])
        ->assertRedirect(route('clients.show', ['id' => $client->id, 'tab' => 'intake']));

    $client->refresh();
    expect($client->eligibility_result)->toBe('Eligible');
    expect($client->eligibility_verified_date?->toDateString())->toBe('2026-06-15');
});

test('Services Requested persists the checked list and can be cleared to none', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    // Save a few services (with the hidden empty marker the form sends).
    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), [
            'section' => 'intake-services',
            'tab' => 'intake',
            'services_requested' => ['', 'Bathing', 'Dressing', 'Mobility'],
        ])->assertRedirect();

    $client->refresh();
    expect($client->services_requested)->toBe(['Bathing', 'Dressing', 'Mobility']);

    // Unchecking everything (only the marker) clears it to an empty list.
    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), [
            'section' => 'intake-services',
            'tab' => 'intake',
            'services_requested' => [''],
        ])->assertRedirect();

    expect($client->fresh()->services_requested)->toBe([]);
});

test('Initial Notes persists free text', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), [
            'section' => 'intake-initialnotes',
            'tab' => 'intake',
            'initial_notes' => 'Daughter is primary contact; prefers morning visits.',
        ])->assertRedirect();

    expect($client->fresh()->initial_notes)->toBe('Daughter is primary contact; prefers morning visits.');
});

test('intake fields cannot be edited on a client from another organization', function () {
    $orgA = $this->createOrganization();
    $orgB = $this->createOrganization();
    $client = $this->createClient($orgB->id);
    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);

    $this->actingAsWithTwoFactor($adminA)
        ->put(route('clients.update', $client->id), [
            'tab' => 'intake',
            'referred_by' => 'Should not save',
        ])->assertForbidden();

    expect($client->fresh()->referred_by)->toBeNull();
});
