<?php

use App\Models\Contact;
use App\Models\User;
use App\Support\DirectoryMcoOptions;

beforeEach(fn () => seedModuleBasics());

test('mco options come from directory payers with a fallback list', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->actingAsWithTwoFactor($admin);

    // No payers on file yet — the well-known Michigan plans remain available.
    expect(DirectoryMcoOptions::list())->toBe(DirectoryMcoOptions::FALLBACK);

    Contact::create([
        'organization_id' => $org->id,
        'name' => 'Molina Healthcare of Michigan',
        'type' => Contact::TYPE_INSURANCE,
        'is_active' => true,
    ]);
    Contact::create([
        'organization_id' => $org->id,
        'name' => 'Inactive Plan',
        'type' => Contact::TYPE_INSURANCE,
        'is_active' => false,
    ]);

    expect(DirectoryMcoOptions::list())->toBe(['Molina Healthcare of Michigan']);
});

test('coordinator picker links the directory contact with a coordinator pivot role', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $coordinator = $this->createContact($org->id, [
        'name' => 'Dana Coordinator',
        'type' => Contact::TYPE_CASE_COORDINATOR,
        'phone' => '(555) 010-2000',
        'is_active' => true,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), [
            'coordinator_contact_id' => $coordinator->id,
            'tab' => 'demographics',
        ])
        ->assertRedirect();

    $client->refresh()->load('contacts');

    expect($client->caseCoordinator()?->id)->toBe($coordinator->id)
        ->and($client->contacts->firstWhere('id', $coordinator->id)->pivot->role)->toBe('Case Coordinator');

    // Re-picking a different coordinator replaces, never duplicates.
    $second = $this->createContact($org->id, [
        'name' => 'New Coordinator',
        'type' => Contact::TYPE_CASE_COORDINATOR,
        'is_active' => true,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), [
            'coordinator_contact_id' => $second->id,
            'tab' => 'demographics',
        ])
        ->assertRedirect();

    $client->refresh()->load('contacts');
    $coordinatorLinks = $client->contacts->filter(fn ($c) => str_contains(strtolower($c->pivot->role ?? ''), 'coordinator'));

    expect($coordinatorLinks)->toHaveCount(1)
        ->and($coordinatorLinks->first()->id)->toBe($second->id);
});

test('asw picker links the directory worker and dhs billing resolves their email', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $asw = $this->createContact($org->id, [
        'name' => 'Alex Worker',
        'type' => Contact::TYPE_AGENCY_STAFF,
        'email' => 'alex.worker@michigan.gov',
        'county' => 'Wayne',
        'is_active' => true,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), [
            'asw_contact_id' => $asw->id,
            'tab' => 'demographics',
        ])
        ->assertRedirect();

    $client->refresh()->load('contacts');

    expect($client->aswContact()?->id)->toBe($asw->id)
        ->and($client->aswContact()->email)->toBe('alex.worker@michigan.gov');

    // The ASW link must never be mistaken for the case coordinator.
    expect($client->caseCoordinator()?->id)->not->toBe($asw->id);
});

test('emergency contact is never mislabeled as the case coordinator', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);

    $emergency = $this->createContact($org->id, [
        'name' => 'Family Member',
        'type' => Contact::TYPE_FAMILY_EMERGENCY,
        'is_active' => true,
    ]);
    $client->contacts()->attach($emergency->id, ['role' => 'emergency · Daughter']);

    $client->load('contacts');

    expect($client->caseCoordinator())->toBeNull();
});

test('employee role cannot update client coordinator links', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $coordinator = $this->createContact($org->id, [
        'name' => 'Dana Coordinator',
        'type' => Contact::TYPE_CASE_COORDINATOR,
        'is_active' => true,
    ]);

    $this->actingAsWithTwoFactor($employee)
        ->put(route('clients.update', $client->id), [
            'coordinator_contact_id' => $coordinator->id,
        ])
        ->assertForbidden();
});
