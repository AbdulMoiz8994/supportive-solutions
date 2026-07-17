<?php

use App\Models\CareDetail;
use App\Models\Client;
use App\Models\Employee;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

// ── Authorizations ──────────────────────────────────────────────────────────
test('guest cannot access the authorizations page', function () {
    $this->get(route('authorizations'))->assertRedirect(route('signin'));
});

test('admin sees the authorizations page with a client auth row', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Authz', 'last_name' => 'Client']);
    CareDetail::create([
        'client_id' => $client->id,
        'organization_id' => $org->id,
        'billing_code' => 'T1019',
        'total_units' => 96,
        'start_date' => now()->subDays(10),
        'end_date' => now()->addDays(10), // expiring within 21 days
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('authorizations'))
        ->assertOk()
        ->assertSee('Authorizations')
        ->assertSee('Authz Client')
        ->assertSee('authRegistry', false);
});

// ── Background Checks ────────────────────────────────────────────────────────
test('guest cannot access the background checks page', function () {
    $this->get(route('background-checks'))->assertRedirect(route('signin'));
});

test('admin sees the background checks matrix with a caregiver row', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $caregiver = $this->createEmployee($org->id, [
        'position' => 'Caregiver',
        'first_name' => 'Checkable',
        'last_name' => 'Caregiver',
    ]);
    $caregiver->backgroundChecks()->create(['organization_id' => $org->id, 'type' => 'CHAMPS', 'status' => 'Clear', 'provider_id' => 'PRV-1']);
    $caregiver->backgroundChecks()->create(['organization_id' => $org->id, 'type' => 'ICHAT', 'status' => 'Clear', 'next_due' => now()->addDays(15)]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('background-checks'))
        ->assertOk()
        ->assertSee('Background Checks')
        ->assertSee('Checkable Caregiver')
        ->assertSee('bgRegistry', false);
});

// ── Compliance & Documents (rebuilt) ─────────────────────────────────────────
test('compliance page renders the two tabs and still works', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Cycle', 'last_name' => 'Client']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('compliance'))
        ->assertOk()
        ->assertSee('Compliance & Documents')
        ->assertSee('Monthly Compliance')
        ->assertSee('Document Hub')
        ->assertSee('Cycle Client');
});
