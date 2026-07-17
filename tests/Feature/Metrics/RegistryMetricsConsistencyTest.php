<?php

use App\Models\Employee;
use App\Models\User;
use App\Services\RegistryMetricsService;

beforeEach(fn () => seedModuleBasics());

test('dashboard and client registry active client counts match', function () {
    $org = $this->createOrganization();
    $otherOrg = $this->createOrganization(['name' => 'Other Org']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->createClient($org->id, ['status' => 'Active', 'first_name' => 'InOrg']);
    $this->createClient($org->id, ['status' => 'On Hold', 'first_name' => 'Held']);
    $this->createClient($otherOrg->id, ['status' => 'Active', 'first_name' => 'Other']);

    $this->actingAsWithTwoFactor($admin);

    $metrics = app(RegistryMetricsService::class);
    $stats = $metrics->clientStats();

    expect($stats['total'])->toBe(2)
        ->and($stats['active'])->toBe(1);

    $this->get(route('dashboard'))
        ->assertOk();

    $this->get(route('clients.index'))
        ->assertOk()
        ->assertSee('1 active');
});

test('dashboard and caregiver registry active caregiver counts match', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->createEmployee($org->id, [
        'position' => 'Caregiver',
        'status' => 'Active',
        'onboarding_status' => 'Complete',
    ]);
    $this->createEmployee($org->id, [
        'position' => 'Caregiver',
        'status' => 'Active',
        'onboarding_status' => 'Pending onboarding',
    ]);
    $this->createEmployee($org->id, [
        'position' => 'Caregiver',
        'status' => 'On Hold',
    ]);

    $this->actingAsWithTwoFactor($admin);

    $stats = app(RegistryMetricsService::class)->caregiverStats();

    expect($stats['total'])->toBe(3)
        ->and($stats['active'])->toBe(1)
        ->and($stats['pending'])->toBe(1)
        ->and($stats['on_hold'])->toBe(1)
        ->and($stats['active'] + $stats['pending'] + $stats['on_hold'] + $stats['on_leave'] + $stats['inactive'])
        ->toBe($stats['total']);

    $this->get(route('caregivers'))
        ->assertOk()
        ->assertSee('3 total')
        ->assertSee('1 active');
});

test('caregiver registry table total matches kpi total when unfiltered', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    Employee::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'first_name' => 'A',
        'last_name' => 'One',
        'position' => 'Caregiver',
        'status' => 'Active',
    ]);
    Employee::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'first_name' => 'B',
        'last_name' => 'Two',
        'position' => 'Caregiver',
        'status' => 'On Hold',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('caregivers'))
        ->assertOk()
        ->assertSee('2 total')
        ->assertSee('of 2');
});

test('client active program subcounts never exceed active total', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->createClient($org->id, ['status' => 'Active', 'first_name' => 'DhsOne']);
    $this->createClient($org->id, ['status' => 'Active', 'first_name' => 'DhsTwo']);
    $this->createClient($org->id, ['status' => 'On Hold', 'first_name' => 'Held']);

    $this->actingAsWithTwoFactor($admin);

    $stats = app(RegistryMetricsService::class)->clientStats();

    expect($stats['dhs'] + $stats['mich'])->toBeLessThanOrEqual($stats['active']);
});

test('DHS authorization past its date reads as reassessment, never expired', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $dhsType = \App\Models\CoverageType::firstOrCreate(['name' => 'DHS Home Help']);
    $michType = \App\Models\CoverageType::firstOrCreate(['name' => 'MICH']);

    $dhs = $this->createClient($org->id, ['status' => 'Active', 'coverage_type_id' => $dhsType->id]);
    $mich = $this->createClient($org->id, ['status' => 'Active', 'coverage_type_id' => $michType->id]);

    foreach ([$dhs, $mich] as $client) {
        \App\Models\CareDetail::create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'start_date' => today()->subMonths(8),
            'end_date' => today()->subDays(5), // in the past
            'status' => 'Active',
            'total_units' => 100,
        ]);
    }

    $dhs->load('coverageType', 'careDetails');
    $mich->load('coverageType', 'careDetails');

    // DHS Time/Task is never "Expired"; the MICH prior-auth genuinely is.
    expect($dhs->authStatus()['label'])->not->toBe('Expired');
    expect($dhs->authStatus()['tone'])->not->toBe('red');
    expect($mich->authStatus()['label'])->toBe('Expired');
    expect($mich->authStatus()['tone'])->toBe('red');

    // The expired-authorization KPI counts only the MICH client.
    $this->actingAsWithTwoFactor($admin);
    expect(app(RegistryMetricsService::class)->clientStats()['auth_expired'])->toBe(1);
});

test('client tab counts align with registry status keys', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->createClient($org->id, ['status' => 'Active', 'first_name' => 'ActiveOne']);
    $this->createClient($org->id, ['status' => 'On Hold', 'first_name' => 'HeldOne']);
    $this->createClient($org->id, ['status' => 'Discharged', 'first_name' => 'DoneOne']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('active DHS', false);

    $metrics = app(RegistryMetricsService::class);
    $clients = $metrics->clients();
    $rows = $clients->map(fn ($c) => [
        'status_key' => \App\Support\ClientRegistryStatus::normalize($c->statusRecord?->name ?? $c->status ?? 'Active'),
        'program' => $c->program_label,
    ]);
    $tabs = $metrics->clientTabCounts($rows);

    expect($tabs['all'])->toBe(3)
        ->and($tabs['active'])->toBe(1)
        ->and($tabs['on_hold'])->toBe(1)
        ->and($tabs['discharged'])->toBe(1);
});

test('caregiver registry header renders all status buckets that sum to total', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->createEmployee($org->id, ['position' => 'Caregiver', 'status' => 'Active', 'onboarding_status' => 'Complete']);
    $this->createEmployee($org->id, ['position' => 'Caregiver', 'status' => 'Active', 'onboarding_status' => 'Pending onboarding']);
    $this->createEmployee($org->id, ['position' => 'Caregiver', 'status' => 'On Leave']);
    $this->createEmployee($org->id, ['position' => 'Caregiver', 'status' => 'On Hold']);
    $this->createEmployee($org->id, ['position' => 'Caregiver', 'status' => 'Terminated']);

    $this->actingAsWithTwoFactor($admin);

    $stats = app(RegistryMetricsService::class)->caregiverStats();

    expect($stats['active'] + $stats['pending'] + $stats['on_hold'] + $stats['on_leave'] + $stats['inactive'])
        ->toBe($stats['total']);

    $this->get(route('caregivers'))
        ->assertOk()
        ->assertSee("{$stats['total']} total", false)
        ->assertSee("{$stats['active']} active", false)
        ->assertSee("{$stats['pending']} pending onboarding", false)
        ->assertSee("{$stats['on_leave']} on leave", false)
        ->assertSee("{$stats['on_hold']} on hold", false)
        ->assertSee("{$stats['inactive']} inactive", false);
});

test('caregiver status buckets are mutually exclusive and sum to total', function () {
    $org = $this->createOrganization();

    $this->createEmployee($org->id, ['position' => 'Caregiver', 'status' => 'Active', 'onboarding_status' => 'Complete']);
    $this->createEmployee($org->id, ['position' => 'Caregiver', 'status' => 'Active', 'onboarding_status' => 'Pending onboarding']);
    $this->createEmployee($org->id, ['position' => 'Caregiver', 'status' => 'On Leave']);
    $this->createEmployee($org->id, ['position' => 'Caregiver', 'status' => 'On Hold']);
    $this->createEmployee($org->id, ['position' => 'Caregiver', 'status' => 'Terminated']);

    $stats = app(RegistryMetricsService::class)->caregiverStats();

    expect($stats['total'])->toBe(5)
        ->and($stats['active'] + $stats['pending'] + $stats['on_hold'] + $stats['on_leave'] + $stats['inactive'])
        ->toBe($stats['total']);
});
