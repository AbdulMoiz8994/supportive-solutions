<?php

use App\Models\Intake;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('leads index renders for admin', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('leads.index'))
        ->assertOk();
});

test('leads store creates intake record', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('leads.store'), [
            'first_name' => 'Legacy',
            'last_name' => 'Lead',
            'email' => 'legacy@example.com',
            'phone' => '(313) 555-0100',
            'source' => 'Website',
        ])
        ->assertRedirect(route('leads.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('intakes', [
        'first_name' => 'Legacy',
        'last_name' => 'Lead',
        'organization_id' => $org->id,
    ]);
});

test('leads store validates required fields', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('leads.store'), ['first_name' => '', 'last_name' => ''])
        ->assertSessionHasErrors(['first_name', 'last_name']);
});

test('leads show displays lead details', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $lead = createTestIntake($org->id, ['first_name' => 'ShowLead', 'last_name' => 'Person']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('leads.show', $lead->id))
        ->assertOk()
        ->assertSee('ShowLead');
});

test('leads update persists changes', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $lead = createTestIntake($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('leads.update', $lead->id), [
            'first_name' => 'Updated',
            'last_name' => 'LeadName',
            'email' => 'updated@example.com',
        ])
        ->assertRedirect(route('leads.index'));

    expect(Intake::withoutGlobalScopes()->find($lead->id)->first_name)->toBe('Updated');
});

test('guest cannot access leads module', function () {
    $this->get(route('leads.index'))->assertRedirect(route('signin'));
});
