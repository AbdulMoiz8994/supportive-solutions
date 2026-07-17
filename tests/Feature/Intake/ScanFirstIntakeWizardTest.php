<?php

use App\Models\CoverageType;
use App\Models\Intake;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('wizard page loads for office staff and is blocked for field employees', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('intakes.wizard'))
        ->assertOk()
        ->assertSee('Scan-first intake')
        ->assertSee('Scan Doc')
        ->assertSee('Check eligibility');

    $this->actingAsWithTwoFactor($employee)
        ->get(route('intakes.wizard'))
        ->assertForbidden();
});

test('eligibility check recommends DHS for straight medicaid and MICH for MCO members', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    CoverageType::firstOrCreate(['name' => 'DHS Home Help']);
    CoverageType::firstOrCreate(['name' => 'MI Choice']);

    $this->actingAsWithTwoFactor($admin);

    // Adult with a valid Medicaid ID and no MCO → eligible, DHS Home Help.
    $straight = $this->postJson(route('intakes.check-eligibility'), [
        'dob' => '1955-03-10',
        'member_id' => 'MD-10001',
        'mco_name' => '',
    ])->assertOk()->json();

    expect($straight['eligibility']['status'])->toBe(Intake::ELIGIBILITY_ELIGIBLE)
        ->and($straight['recommendation']['program'])->toBe('DHS Home Help')
        ->and($straight['recommendation']['coverage_type_id'])->not->toBeNull();

    // MCO member → MICH.
    $mco = $this->postJson(route('intakes.check-eligibility'), [
        'dob' => '1960-06-01',
        'member_id' => 'MD-10002',
        'mco_name' => 'Molina Healthcare',
    ])->assertOk()->json();

    expect($mco['recommendation']['program'])->toBe('MICH');

    // Missing Medicaid ID → needs verification; minor → ineligible.
    $missing = $this->postJson(route('intakes.check-eligibility'), ['dob' => '1950-01-01'])->json();
    $minor = $this->postJson(route('intakes.check-eligibility'), ['dob' => now()->subYears(10)->toDateString()])->json();

    expect($missing['eligibility']['status'])->toBe(Intake::ELIGIBILITY_NEEDS_VERIFICATION)
        ->and($minor['eligibility']['status'])->toBe(Intake::ELIGIBILITY_INELIGIBLE);
});

test('wizard store persists scan, eligibility and program data', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $dhs = CoverageType::firstOrCreate(['name' => 'DHS Home Help']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.store'), [
            'from_wizard' => 1,
            'first_name' => 'Scan',
            'last_name' => 'Lead',
            'dob' => '1950-04-02',
            'phone' => '(313) 555-0100',
            'member_id' => 'MD-20001',
            'address' => '100 Main St, Detroit, MI',
            'mco_name' => '',
            'source' => 'Hospital referral',
            'scan_data' => json_encode(['fields' => ['first_name' => 'Scan', 'last_name' => 'Lead']]),
            'eligibility_status' => Intake::ELIGIBILITY_ELIGIBLE,
            'eligibility_note' => 'Medicaid ID format valid.',
            'eligibility_checked_at' => now()->toIso8601String(),
            'recommended_program' => 'DHS Home Help',
            'coverage_type_id' => $dhs->id,
        ])
        ->assertRedirect();

    $intake = Intake::withoutGlobalScopes()->where('last_name', 'Lead')->first();

    expect($intake)->not->toBeNull()
        ->and($intake->member_id)->toBe('MD-20001')
        ->and($intake->eligibility_status)->toBe(Intake::ELIGIBILITY_ELIGIBLE)
        ->and($intake->recommended_program)->toBe('DHS Home Help')
        ->and($intake->coverage_type_id)->toBe($dhs->id)
        ->and(data_get($intake->scan_data, 'fields.first_name'))->toBe('Scan')
        ->and($intake->displayStatus())->toBe('Converted')
        ->and($intake->converted_client_id)->not->toBeNull();
});

test('convert sets intake status to Converted for KPI tracking', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    \App\Models\AiAgent::withoutGlobalScopes()
        ->where('organization_id', $org->id)
        ->where('slug', 'intake')
        ->update(['is_enabled' => false]);

    $intake = createTestIntake($org->id, ['first_name' => 'Kpi', 'last_name' => 'Track']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.convert', $intake->id))
        ->assertRedirect();

    $intake->refresh();
    expect($intake->status)->toBe('Converted')
        ->and($intake->displayStatus())->toBe('Converted');
});

test('intake agent queues eligibility review when verification is needed', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    app(\App\Services\AiAgentRegistryService::class)->ensureCatalog($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.store'), [
            'from_wizard' => 1,
            'first_name' => 'Needs',
            'last_name' => 'Review',
            'eligibility_status' => Intake::ELIGIBILITY_NEEDS_VERIFICATION,
            'eligibility_note' => 'Missing Medicaid ID.',
        ])
        ->assertRedirect();

    $intake = Intake::withoutGlobalScopes()->where('last_name', 'Review')->first();

    expect($intake)->not->toBeNull()
        ->and($intake->converted_client_id)->toBeNull()
        ->and(\App\Models\WorkflowQueueItem::where('slug', 'intake-eligibility-'.$intake->id)->exists())->toBeTrue();
});

test('intake agent auto-builds chart and holds client for activation approval', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $dhs = CoverageType::firstOrCreate(['name' => 'DHS Home Help']);

    app(\App\Services\AiAgentRegistryService::class)->ensureCatalog($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.store'), [
            'from_wizard' => 1,
            'first_name' => 'Agent',
            'last_name' => 'Built',
            'member_id' => 'MD-40001',
            'eligibility_status' => Intake::ELIGIBILITY_ELIGIBLE,
            'coverage_type_id' => $dhs->id,
        ])
        ->assertRedirect();

    $intake = Intake::withoutGlobalScopes()->where('last_name', 'Built')->first();
    $client = \App\Models\Client::withoutGlobalScopes()->find($intake->converted_client_id);

    expect($client)->not->toBeNull()
        ->and($client->status)->toBe('Hold')
        ->and($client->member_id)->toBe('MD-40001')
        ->and($intake->displayStatus())->toBe('Converted');
});

test('acceptance: referral upload through wizard creates client in registry and converts intake', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $dhs = CoverageType::firstOrCreate(['name' => 'DHS Home Help']);

    app(\App\Services\AiAgentRegistryService::class)->ensureCatalog($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.store'), [
            'from_wizard' => 1,
            'first_name' => 'Referral',
            'last_name' => 'Acceptance',
            'dob' => '1948-11-20',
            'phone' => '(313) 555-8800',
            'member_id' => 'MD-88001',
            'address' => '200 Oak St, Detroit, MI',
            'mco_name' => '',
            'source' => 'Hospital discharge',
            'scanned_documents' => json_encode([
                ['slot' => 'medicaid', 'filename' => 'medicaid.jpg'],
                ['slot' => 'id', 'filename' => 'id.jpg'],
            ]),
            'scan_data' => json_encode(['fields' => ['first_name' => 'Referral', 'last_name' => 'Acceptance']]),
            'eligibility_status' => Intake::ELIGIBILITY_ELIGIBLE,
            'eligibility_note' => 'Medicaid ID format valid.',
            'eligibility_checked_at' => now()->toIso8601String(),
            'recommended_program' => 'DHS Home Help',
            'program_track' => 'dhs',
            'hours_per_week' => 28,
            'coverage_type_id' => $dhs->id,
        ])
        ->assertRedirect();

    $intake = Intake::withoutGlobalScopes()->where('last_name', 'Acceptance')->first();
    $client = \App\Models\Client::withoutGlobalScopes()->find($intake->converted_client_id);

    expect($intake)->not->toBeNull()
        ->and($intake->displayStatus())->toBe('Converted')
        ->and($intake->program_track)->toBe('dhs')
        ->and($intake->hours_per_week)->toBe(28.0)
        ->and($client)->not->toBeNull()
        ->and($client->member_id)->toBe('MD-88001');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Referral Acceptance');

    expect(\App\Models\CareDetail::withoutGlobalScopes()->where('client_id', $client->id)->exists())->toBeTrue()
        ->and(\App\Models\WorkflowQueueItem::where('slug', 'intake-compliance-docs-'.$client->id)->exists())->toBeTrue();
});

test('acceptance: MICH path queues PA submission and creates pending authorization', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $mich = CoverageType::firstOrCreate(['name' => 'MI Choice']);

    app(\App\Services\AiAgentRegistryService::class)->ensureCatalog($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.store'), [
            'from_wizard' => 1,
            'first_name' => 'Mich',
            'last_name' => 'Path',
            'member_id' => 'MD-88002',
            'mco_name' => 'Molina Healthcare',
            'eligibility_status' => Intake::ELIGIBILITY_ELIGIBLE,
            'recommended_program' => 'MICH',
            'program_track' => 'mich',
            'pa_units' => 480,
            'coverage_type_id' => $mich->id,
        ])
        ->assertRedirect();

    $intake = Intake::withoutGlobalScopes()->where('last_name', 'Path')->first();
    $client = \App\Models\Client::withoutGlobalScopes()->find($intake->converted_client_id);
    $auth = \App\Models\CareDetail::withoutGlobalScopes()->where('client_id', $client->id)->first();

    expect($auth)->not->toBeNull()
        ->and($auth->status)->toBe('Pending')
        ->and($auth->total_units)->toBe(480)
        ->and(\App\Models\WorkflowQueueItem::where('slug', 'intake-pa-submit-'.$auth->id)->exists())->toBeTrue();
});

test('legacy quick-add intake still works with only the four original fields', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.store'), [
            'first_name' => 'Quick',
            'last_name' => 'Add',
            'phone' => '(313) 555-0200',
            'source' => 'Website',
        ])
        ->assertRedirect(route('intakes.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('intakes', ['first_name' => 'Quick', 'last_name' => 'Add']);
});

test('convert carries the wizard program and medicaid id onto the client', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $dhs = CoverageType::firstOrCreate(['name' => 'DHS Home Help']);

    \App\Models\AiAgent::withoutGlobalScopes()
        ->where('organization_id', $org->id)
        ->where('slug', 'intake')
        ->update(['is_enabled' => false]);

    $intake = createTestIntake($org->id, [
        'first_name' => 'Convert',
        'last_name' => 'Ready',
        'member_id' => 'MD-30001',
        'address' => '5 Elm St',
        'recommended_program' => 'DHS Home Help',
        'coverage_type_id' => $dhs->id,
        'eligibility_status' => Intake::ELIGIBILITY_ELIGIBLE,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.convert', $intake->id))
        ->assertRedirect();

    $client = \App\Models\Client::withoutGlobalScopes()
        ->where('last_name', 'Ready')
        ->first();

    expect($client)->not->toBeNull()
        ->and($client->member_id)->toBe('MD-30001')
        ->and($client->coverage_type_id)->toBe($dhs->id)
        ->and($intake->fresh()->converted_client_id)->toBe($client->id);

    // Converting twice is rejected.
    $this->actingAsWithTwoFactor($admin)
        ->post(route('intakes.convert', $intake->id))
        ->assertRedirect()
        ->assertSessionHas('error');
});
