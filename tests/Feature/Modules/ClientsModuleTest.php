<?php

use App\Models\CaregiverAssignment;
use App\Models\Client;
use App\Models\CoverageType;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

function clientEnrolPayload(array $overrides = []): array
{
    $coverageType = CoverageType::query()->first() ?? CoverageType::create(['name' => 'DHS Home Help']);

    return array_merge([
        'first_name' => 'Enrol',
        'last_name' => 'Client',
        'phone' => '(313) 555-0201',
        'address' => '100 Main St',
        'county' => 'Wayne',
        'coverage_type_id' => $coverageType->id,
    ], $overrides);
}

test('guest cannot access client module routes', function () {
    $this->get(route('clients.index'))->assertRedirect(route('signin'));
    $this->post(route('clients.store'), clientEnrolPayload())->assertRedirect(route('signin'));
});

test('client enrol store rejects invalid medicaid id format', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.store'), clientEnrolPayload(['member_id' => '12345']))
        ->assertSessionHasErrors(['member_id']);
});

test('client enrol store creates record with valid medicaid id', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.store'), clientEnrolPayload(['member_id' => 'MD-10042']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('clients', [
        'member_id' => 'MD-10042',
        'organization_id' => $org->id,
    ]);
});

test('client update persists dropdown fields', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['gender' => 'Male', 'preferred_language' => 'English']);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), [
            'gender' => 'Female',
            'preferred_language' => 'Arabic',
            'tab' => 'demographics',
        ])
        ->assertRedirect();

    $client->refresh();
    expect($client->gender)->toBe('Female')
        ->and($client->preferred_language)->toBe('Arabic');
});

test('client assign caregiver creates active assignment relationship', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.assign-caregiver', $client->id), [
            'employee_id' => $caregiver->id,
            'relationship' => 'Daughter',
        ])
        ->assertRedirect();

    expect(CaregiverAssignment::where('client_id', $client->id)->where('status', 'Active')->exists())->toBeTrue();
});

test('client schedule tab lists linked visits', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $caregiver = $this->createEmployee($org->id);
    $this->createSchedule($org->id, $client->id, $caregiver->id, ['title' => 'Home Visit Module']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.show', ['id' => $client->id, 'tab' => 'schedule']))
        ->assertOk()
        ->assertSee('Home Visit Module')
        ->assertSee('Visits / Schedule');
});

test('client destroy removes record', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->delete(route('clients.destroy', $client->id))
        ->assertRedirect(route('clients.index'));

    expect(Client::withoutGlobalScopes()->find($client->id))->toBeNull();
});

test('client show returns forbidden for cross organization access', function () {
    $orgA = $this->createOrganization();
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $client = $this->createClient($orgA->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $this->actingAsWithTwoFactor($adminB)
        ->get(route('clients.show', $client->id))
        ->assertForbidden();
});

test('client care detail store creates authorization row', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.care-details.store', $client->id), [
            'billing_code' => 'T1019',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'total_units' => 320,
            'authorized_by' => 'Dr. Smith',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('care_details', ['client_id' => $client->id, 'billing_code' => 'T1019']);
});

test('client create wizard page loads program options', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.create'))
        ->assertOk()
        ->assertSee('Enrol Client')
        ->assertSee('Coverage');
});
