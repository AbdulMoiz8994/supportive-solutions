<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\Employee;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('guest cannot access compliance module', function () {
    $this->get(route('compliance'))->assertRedirect(route('signin'));
});

test('admin can view compliance overview page', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('compliance'))
        ->assertOk()
        ->assertSee('Compliance');
});

test('compliance page lists client and caregiver subjects for upload picker', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Picker', 'last_name' => 'Client']);
    $caregiver = $this->createEmployee($org->id, [
        'position' => 'Caregiver',
        'first_name' => 'Picker',
        'last_name' => 'Caregiver',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('compliance'))
        ->assertOk()
        ->assertSee('Picker Client')
        ->assertSee('Picker Caregiver');
});

test('compliance buckets reflect document verification states', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $this->createDocument($org->id, $client, [
        'verification_status' => 'Pending',
        'name' => 'Pending License',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('compliance'))
        ->assertOk()
        ->assertSee('Pending License');
});

test('audit view lists activity logs for organization', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    \App\Models\ActivityLog::create([
        'organization_id' => $org->id,
        'user_id' => $admin->id,
        'action' => 'Created',
        'subject_type' => Client::class,
        'subject_id' => 1,
        'description' => 'Test audit entry',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('audit-view'))
        ->assertOk()
        ->assertSee('Test audit entry');
});

test('document verify action updates verification status', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $document = $this->createDocument($org->id, $client, ['verification_status' => 'Pending']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('documents.verify', $document->id))
        ->assertRedirect();

    expect($document->fresh()->verification_status)->toBe('Verified');
});

test('compliance document relationship resolves polymorphic subject', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $document = $this->createDocument($org->id, $client);

    expect($document->documentable)->toBeInstanceOf(Client::class)
        ->and($document->documentable->id)->toBe($client->id);

    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);
    $cgDoc = $this->createDocument($org->id, $caregiver);

    expect($cgDoc->documentable)->toBeInstanceOf(Employee::class);
});
