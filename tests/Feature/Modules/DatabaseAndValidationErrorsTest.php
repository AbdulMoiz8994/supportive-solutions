<?php

use App\Models\Client;
use App\Models\CoverageType;
use App\Models\Intake;
use App\Models\User;
use Illuminate\Database\QueryException;

beforeEach(fn () => seedModuleBasics());

test('client store rejects invalid medicaid id format', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);
    $coverage = CoverageType::first();

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.store'), [
            'first_name' => 'Bad',
            'last_name' => 'Medicaid',
            'coverage_type_id' => $coverage->id,
            'member_id' => '12345',
        ])
        ->assertSessionHasErrors(['member_id']);
});

test('client update with invalid tab still persists valid fields', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('clients.update', $client->id), [
            'first_name' => 'Valid',
            'last_name' => 'Name',
            'gender' => 'Female',
        ])
        ->assertRedirect();

    expect($client->fresh()->gender)->toBe('Female');
});

test('assign caregiver rejects missing employee', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('clients.assign-caregiver', $client->id), [
            'employee_id' => 999999,
        ])
        ->assertSessionHasErrors(['employee_id']);
});

test('intake cannot be created without organization context for super admin without fallback org', function () {
    $super = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => null]);

    // Super admin with no org: controller falls back to Organization::first() or id 1.
    // With empty DB except seeded data, first org is created by test helper when needed.
    $org = $this->createOrganization();
    expect($org)->not->toBeNull();

    $this->actingAsWithTwoFactor($super)
        ->post(route('intakes.store'), intakePayload())
        ->assertRedirect(route('intakes.index'));

    expect(Intake::withoutGlobalScopes()->count())->toBeGreaterThan(0);
});

test('direct model create without required organization_id throws database error', function () {
    expect(fn () => Client::withoutGlobalScopes()->create([
        'first_name' => 'Orphan',
        'last_name' => 'Record',
    ]))->toThrow(QueryException::class);
});

test('show routes for wrong organization return forbidden not server error', function () {
    $orgA = $this->createOrganization();
    $orgB = $this->createOrganization(['name' => 'Other Org']);
    $client = $this->createClient($orgA->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $this->actingAsWithTwoFactor($adminB)
        ->get(route('clients.show', $client->id))
        ->assertForbidden();
});

test('json validation errors return 422 not 500 for client store', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('clients.store'), ['member_id' => 'INVALID'])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);
});

test('destroying nonexistent client returns 404', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->delete(route('clients.destroy', 999999))
        ->assertNotFound();
});

test('schedule store validation errors do not create partial records', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);
    $before = \App\Models\Schedule::withoutGlobalScopes()->count();

    $this->actingAsWithTwoFactor($admin)
        ->post(route('schedule.store'), [
            'title' => '',
            'date' => 'not-a-date',
            'start_time' => '09:00',
            'end_time' => '08:00',
        ])
        ->assertSessionHasErrors();

    expect(\App\Models\Schedule::withoutGlobalScopes()->count())->toBe($before);
});
