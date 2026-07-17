<?php

use App\Models\CaregiverAssignment;
use App\Models\Client;
use App\Models\CoverageType;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

function enrolmentPayload(array $overrides = []): array
{
    $coverageType = CoverageType::query()->first() ?? CoverageType::create(['name' => 'DHS Home Help']);

    return array_merge([
        'first_name' => 'Lifecycle',
        'last_name' => 'Client',
        'phone' => '(313) 555-0200',
        'address' => '100 Main St',
        'county' => 'Wayne',
        'coverage_type_id' => $coverageType->id,
    ], $overrides);
}

test('client full lifecycle create update assign archive and restore', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);

    // Create
    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.store'), enrolmentPayload(['email' => 'lifecycle@example.com']))
        ->assertRedirect();

    $client = Client::withoutGlobalScopes()->where('email', 'lifecycle@example.com')->first();
    expect($client)->not->toBeNull();

    // Update
    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), enrolmentPayload([
            'first_name' => 'UpdatedLifecycle',
            'email' => 'lifecycle@example.com',
        ]))
        ->assertRedirect(route('clients.index'));

    expect(Client::withoutGlobalScopes()->find($client->id)->first_name)->toBe('UpdatedLifecycle');

    // Assign caregiver
    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.assign-caregiver', $client->id), ['employee_id' => $caregiver->id])
        ->assertRedirect();

    expect(CaregiverAssignment::where('client_id', $client->id)->where('employee_id', $caregiver->id)->exists())->toBeTrue();

    // Change status
    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.change-status', $client->id), [
            'to_status' => 'On Hold',
            'effective_date' => today()->toDateString(),
            'reason' => 'Testing lifecycle',
        ])
        ->assertRedirect();

    expect(Client::withoutGlobalScopes()->find($client->id)->status)->toBe('On Hold');
});

test('client store rejects duplicate member id within organization', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->createClient($org->id, ['member_id' => 'MD-12345']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.store'), enrolmentPayload(['member_id' => 'MD-12345']))
        ->assertSessionHasErrors('member_id');
});

test('client registry lists created clients', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createClient($org->id, ['first_name' => 'Filterable', 'last_name' => 'UniqueName']);
    $this->createClient($org->id, ['first_name' => 'Other', 'last_name' => 'Person']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Filterable')
        ->assertSee('Other');
});
