<?php

use App\Models\Billing;
use App\Models\BillingClaimAudit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('dashboard approve decrements banner totals when more pending billings exist than queue preview', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Queue', 'last_name' => 'Client']);

    $billings = collect(range(1, 8))->map(function (int $index) use ($org, $client) {
        return Billing::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-BULK-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'total_amount' => 100 + $index,
            'status' => 'Pending',
        ]);
    });

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('8 billing holds', false);

    $response = $this->actingAsWithTwoFactor($admin)
        ->postJson(route('dashboard.approve', ['type' => 'billing', 'id' => $billings->first()->id]))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('approvalCount', 7)
        ->assertJsonCount(6, 'approvals');

    expect(collect($response->json('approvalChips'))->firstWhere('label', '7 billing holds'))->not->toBeNull();
});

test('dashboard approve returns refreshed approval counters in json', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Queue', 'last_name' => 'Client']);

    $billing = Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-QUEUE-001',
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'total_amount' => 250.00,
        'status' => 'Pending',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('dashboard.approve', ['type' => 'billing', 'id' => $billing->id]))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('approvalCount', 0)
        ->assertJsonStructure(['approvals', 'approvalChips']);

    $this->assertDatabaseHas('billings', [
        'id' => $billing->id,
        'status' => 'Sent',
    ]);
});

test('dashboard review link resolves claim by claim number when invoice number differs', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Claim', 'last_name' => 'Match']);

    $periodStart = now()->subMonth()->startOfMonth();
    $periodEnd = $periodStart->copy()->endOfMonth();

    $claim = BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'claim_number' => 'CLM-NUM-ONLY-001',
        'invoice_number' => null,
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => $periodStart->toDateString(),
        'period_start' => $periodStart->toDateString(),
        'period_end' => $periodEnd->toDateString(),
        'hourly_rate' => 20,
        'total_amount' => 100,
        'submission_channel' => 'Availity',
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
        'billing_status' => BillingClaimAudit::BILLING_BLOCKED,
    ]);

    Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'CLM-NUM-ONLY-001',
        'period_start' => $periodStart->toDateString(),
        'period_end' => $periodEnd->toDateString(),
        'total_amount' => 100,
        'status' => 'Pending',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('CLM-NUM-ONLY-001', false)
        ->assertSee('billing-claims-audit', false);
});

test('dashboard lists blocked billing claim audits with direct review links', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Blocked', 'last_name' => 'Claim']);

    $claim = BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'claim_number' => 'CLM-BLOCK-001',
        'program_type' => BillingClaimAudit::PROGRAM_DHS,
        'submission_channel' => 'Sigma',
        'billing_period' => now()->startOfMonth()->toDateString(),
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'hourly_rate' => 20,
        'total_amount' => 100,
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
        'billing_status' => BillingClaimAudit::BILLING_BLOCKED,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('CLM-BLOCK-001', false)
        ->assertSee('CP-01', false)
        ->assertSee('blocked', false)
        ->assertSee('billing-claims-audit', false);
});

test('dashboard review link targets claim show page when claim exists', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Review', 'last_name' => 'Target']);

    $periodStart = now()->startOfMonth();
    $periodEnd = $periodStart->copy()->endOfMonth();

    $claim = BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'claim_number' => 'CLM-REV-001',
        'invoice_number' => 'INV-REV-001',
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => $periodStart->toDateString(),
        'period_start' => $periodStart->toDateString(),
        'period_end' => $periodEnd->toDateString(),
        'hourly_rate' => 20,
        'total_amount' => 100,
        'submission_channel' => 'Availity',
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
        'billing_status' => BillingClaimAudit::BILLING_BLOCKED,
    ]);

    Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-REV-001',
        'period_start' => $periodStart->toDateString(),
        'period_end' => $periodEnd->toDateString(),
        'total_amount' => 100,
        'status' => 'Pending',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('INV-REV-001', false)
        ->assertSee('billing-claims-audit', false);
});

test('dashboard new intake action points to intake pipeline only', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('intakes.index'), false)
        ->assertDontSee(route('clients.create'), false)
        ->assertDontSee('New Client / Intake', false);
});

test('dashboard pa renewal review links to client authorization tab', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Renewal', 'last_name' => 'Client']);

    \App\Models\CareDetail::create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'billing_code' => 'T1019',
        'start_date' => now()->subMonths(5),
        'end_date' => now()->addDays(14),
        'total_units' => 400,
        'hours_per_week' => 25,
        'status' => 'Expiring',
    ]);

    $careDetail = \App\Models\CareDetail::where('client_id', $client->id)->first();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('tab=authorization', false)
        ->assertSee('care_detail='.$careDetail->id, false);
});

test('dashboard billing hold without claim links to client billing tab', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Legacy', 'last_name' => 'Billing']);

    $billing = Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-LEGACY-ONLY-001',
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'total_amount' => 180.00,
        'status' => 'Pending',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('tab=billing', false)
        ->assertSee('billing='.$billing->id, false)
        ->assertDontSee('status=on_hold', false);
});

test('dashboard background flag review links to caregiver checks tab', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $caregiver = $this->createEmployee($org->id, [
        'position' => 'Caregiver',
        'has_background_check' => 0,
        'first_name' => 'Flagged',
        'last_name' => 'Caregiver',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Background flag', false)
        ->assertSee('tab=checks', false);
});
