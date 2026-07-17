<?php

use App\Models\CareDetail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function makeCareDetail(array $attrs = []): CareDetail
{
    return new CareDetail(array_merge([
        'billing_code' => 'T1019',
        'total_units' => 56,
        'start_date' => now()->subDays(10),
        'end_date' => now()->addDays(30),
    ], $attrs));
}

// ─── Hours math (no LLM — pure backend automation) ──────────────────────────

test('weekly hours derive from 15-minute units (T1019 units ÷ 4)', function () {
    expect(makeCareDetail(['total_units' => 56])->hours_per_week_value)->toBe(14.0);
    expect(makeCareDetail(['total_units' => 40])->hours_per_week_value)->toBe(10.0);
});

test('stored weekly hours take precedence over the unit derivation', function () {
    expect(makeCareDetail(['total_units' => 56, 'hours_per_week' => 20])->hours_per_week_value)->toBe(20.0);
});

test('per-day and per-month hours derive from weekly hours', function () {
    $cd = makeCareDetail(['total_units' => 56]); // 14 hrs/wk
    expect($cd->hours_per_day)->toBe(2.0);        // 14 ÷ 7
    expect($cd->hours_per_month)->toBe(60.8);     // 14 × (365/12/7)
});

test('hours are null when neither units nor weekly hours are set', function () {
    $cd = makeCareDetail(['total_units' => null, 'hours_per_week' => null]);
    expect($cd->hours_per_week_value)->toBeNull();
    expect($cd->hours_per_day)->toBeNull();
});

// ─── Expiry / renewal automation ────────────────────────────────────────────

test('authorization inside the 45-day window needs renewal', function () {
    $cd = makeCareDetail(['end_date' => now()->addDays(30)]);
    expect($cd->needs_renewal)->toBeTrue();
    expect($cd->is_expired)->toBeFalse();
    expect($cd->effective_status)->toBe('Expiring Soon');
});

test('authorization beyond the renewal window is active', function () {
    $cd = makeCareDetail(['end_date' => now()->addDays(100)]);
    expect($cd->needs_renewal)->toBeFalse();
    expect($cd->effective_status)->toBe('Active');
});

test('past end date flags the authorization expired', function () {
    $cd = makeCareDetail(['end_date' => now()->subDays(2)]);
    expect($cd->is_expired)->toBeTrue();
    expect($cd->effective_status)->toBe('Expired');
});

test('DHS past reassessment date reads reassessment due, never expired', function () {
    $org = test()->createOrganization();
    $dhsType = \App\Models\CoverageType::firstOrCreate(['name' => 'DHS Home Help']);
    $client = test()->createClient($org->id, ['coverage_type_id' => $dhsType->id]);

    $cd = CareDetail::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'billing_code' => 'T019',
        'start_date' => today()->subMonths(8),
        'end_date' => today()->subDays(5),
        'total_units' => 100,
        'status' => 'Active',
    ]);

    $cd->load('client');

    expect($cd->effective_status)->toBe('Reassessment due')
        ->and($cd->effectiveStatusForProgram('DHS'))->toBe('Reassessment due')
        ->and($cd->authRefForProgram('DHS'))->toStartWith('TT-')
        ->and($cd->authRefForProgram('MICH'))->toStartWith('PA-');
});

// ─── End-to-end: storing a care detail computes & persists weekly hours ──────

test('storing a care detail computes weekly hours and exposes per-day hours', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->post(route('clients.care-details.store', $client->id), [
            'billing_code' => 'T1019',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'total_units' => 56,
        ])
        ->assertRedirect();

    $cd = $client->careDetails()->withoutGlobalScopes()->first();
    expect($cd)->not->toBeNull();
    expect((float) $cd->hours_per_week)->toBe(14.0);
    expect($cd->hours_per_day)->toBe(2.0);
    expect($cd->effective_status)->toBe('Active');
});

// ─── Inline edit persistence (the Authorization Details "Save" bug) ───────────

test('editing an authorization persists the new units and recomputes weekly hours', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $cd = $client->careDetails()->create([
        'billing_code' => 'T1019',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(5)->toDateString(),
        'total_units' => 112,
        'hours_per_week' => 28,
        'status' => 'Active',
        'organization_id' => $org->id,
    ]);

    $this->actingAsWithTwoFactor($super)
        ->put(route('clients.care-details.update', ['id' => $client->id, 'careDetail' => $cd->id]), [
            'billing_code' => 'T1019',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(5)->toDateString(),
            'total_units' => 120,
            'tab' => 'authorization',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Changes saved.');

    // Persisted to the database — survives a reload.
    $cd->refresh();
    expect($cd->total_units)->toBe(120);
    // Weekly hours recompute from the edited units (120 ÷ 4 = 30).
    expect((float) $cd->hours_per_week)->toBe(30.0);
});

test('editing an authorization is blocked for a client in another organization', function () {
    $orgA = $this->createOrganization();
    $orgB = $this->createOrganization();
    $client = $this->createClient($orgB->id);
    $cd = $client->careDetails()->create([
        'billing_code' => 'T1019',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonths(6)->toDateString(),
        'total_units' => 56,
        'status' => 'Active',
        'organization_id' => $orgB->id,
    ]);

    // An admin scoped to org A must not edit org B's authorization.
    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);

    $this->actingAsWithTwoFactor($adminA)
        ->put(route('clients.care-details.update', ['id' => $client->id, 'careDetail' => $cd->id]), [
            'billing_code' => 'T1019',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'total_units' => 200,
        ])
        ->assertForbidden();

    expect($cd->fresh()->total_units)->toBe(56);
});
