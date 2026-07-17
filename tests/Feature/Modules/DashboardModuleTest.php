<?php

use App\Models\Billing;
use App\Models\BillingClaimAudit;
use App\Models\Schedule;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('guest cannot access dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('signin'));
});

test('dashboard loads with kpi strip for admin', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->createClient($org->id, ['status' => 'Active']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Live Dashboard');
});

test('dashboard approve billing hold updates invoice and returns json counters', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $billing = Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-DASH-001',
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'total_amount' => 500,
        'status' => 'Pending',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('dashboard.approve', ['type' => 'billing', 'id' => $billing->id]))
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($billing->fresh()->status)->toBe('Sent');
});

test('dashboard review links to billing claim show when claim exists', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Dash', 'last_name' => 'Review']);
    $periodStart = now()->startOfMonth();

    $claim = BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'claim_number' => 'CLM-DASH-001',
        'invoice_number' => 'INV-DASH-REVIEW',
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => $periodStart->toDateString(),
        'period_start' => $periodStart->toDateString(),
        'period_end' => $periodStart->copy()->endOfMonth()->toDateString(),
        'hourly_rate' => 30,
        'total_amount' => 300,
        'submission_channel' => 'Availity',
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
        'billing_status' => BillingClaimAudit::BILLING_BLOCKED,
    ]);

    Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-DASH-REVIEW',
        'period_start' => $periodStart->toDateString(),
        'period_end' => $periodStart->copy()->endOfMonth()->toDateString(),
        'total_amount' => 300,
        'status' => 'Pending',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('INV-DASH-REVIEW', false)
        ->assertSee('billing-claims-audit', false);
});

test('dashboard send efax quick action routes to efax composer', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('communications.index', ['compose' => 'efax']), false);
});

test('dashboard approve rejects unknown type with error', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('dashboard.approve', ['type' => 'unknown', 'id' => 1]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('employee without dashboard permission cannot access dashboard', function () {
    $employee = $this->createUser(User::ROLE_EMPLOYEE);
    $role = \App\Models\Role::where('slug', 'employee')->first();
    $role->permissions()->detach(
        \App\Models\Permission::where('slug', 'view_dashboard')->pluck('id')
    );

    $this->actingAsWithTwoFactor($employee)
        ->get(route('dashboard'))
        ->assertForbidden();
});
