<?php

use App\Models\Billing;
use App\Models\BillingClaimAudit;
use App\Models\User;
use App\Services\ApprovalQueueMetricsService;
use App\Services\BillingClaimsAuditService;
use App\Services\RegistryMetricsService;

beforeEach(fn () => seedModuleBasics());

test('dashboard billed kpi includes pending invoices without claim audits', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Invoice', 'last_name' => 'Only']);
    $period = now()->startOfMonth();

    Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-2026-0006',
        'period_start' => $period->toDateString(),
        'period_end' => $period->copy()->endOfMonth()->toDateString(),
        'total_amount' => 148,
        'status' => 'Pending',
    ]);

    $stats = app(RegistryMetricsService::class)->billingClaimStats($org->id, $period);

    expect($stats['billed_amount'])->toBe(148.0)
        ->and($stats['outstanding_amount'])->toBe(148.0)
        ->and($stats['collected_amount'])->toBe(0.0)
        ->and($stats['in_flight'])->toBe(1);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('$148', false)
        ->assertSee('INV-2026-0006', false);
});

test('dashboard money kpis do not double count invoices already represented by claim audits', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $period = now()->startOfMonth();

    BillingClaimAudit::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'claim_number' => 'CLM-MATCH-001',
        'invoice_number' => 'INV-MATCH-001',
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => $period->toDateString(),
        'period_start' => $period->toDateString(),
        'period_end' => $period->copy()->endOfMonth()->toDateString(),
        'hourly_rate' => 30,
        'total_amount' => 300,
        'submission_channel' => 'Availity',
        'claim_status' => BillingClaimAudit::STATUS_AWAITING_PAYMENT,
        'billing_status' => BillingClaimAudit::BILLING_PENDING_PAYMENT,
    ]);

    Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-MATCH-001',
        'period_start' => $period->toDateString(),
        'period_end' => $period->copy()->endOfMonth()->toDateString(),
        'total_amount' => 300,
        'status' => 'Pending',
    ]);

    $stats = app(RegistryMetricsService::class)->billingClaimStats($org->id, $period);

    expect($stats['billed_amount'])->toBe(300.0)
        ->and($stats['outstanding_amount'])->toBe(300.0)
        ->and($stats['in_flight'])->toBe(1);
});

test('dashboard collected kpi includes paid invoices without claim audits', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $period = now()->startOfMonth();

    Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-PAID-ONLY',
        'period_start' => $period->toDateString(),
        'period_end' => $period->copy()->endOfMonth()->toDateString(),
        'total_amount' => 592,
        'status' => 'Paid',
    ]);

    $stats = app(RegistryMetricsService::class)->billingClaimStats($org->id, $period);

    expect($stats['billed_amount'])->toBe(592.0)
        ->and($stats['collected_amount'])->toBe(592.0)
        ->and($stats['outstanding_amount'])->toBe(0.0)
        ->and($stats['in_flight'])->toBe(0);
});

test('invoices outside the dashboard period do not affect current month money kpis', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $period = now()->startOfMonth();
    $lastMonth = now()->subMonth()->startOfMonth();

    Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-OLD-HOLD',
        'period_start' => $lastMonth->toDateString(),
        'period_end' => $lastMonth->copy()->endOfMonth()->toDateString(),
        'total_amount' => 148,
        'status' => 'Pending',
    ]);

    $stats = app(RegistryMetricsService::class)->billingClaimStats($org->id, $period);

    expect($stats['billed_amount'])->toBe(0.0)
        ->and($stats['outstanding_amount'])->toBe(0.0);
});

test('billing page summary cards use the same period dollar totals as the dashboard', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Shared', 'last_name' => 'Totals']);
    $period = now()->startOfMonth();

    Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-2026-0003',
        'period_start' => $period->toDateString(),
        'period_end' => $period->copy()->endOfMonth()->toDateString(),
        'total_amount' => 608,
        'status' => 'Pending',
    ]);

    $metrics = app(ApprovalQueueMetricsService::class);
    $summary = app(BillingClaimsAuditService::class)->summaryForPeriod($org->id, $period);
    $stats = app(RegistryMetricsService::class)->billingClaimStats($org->id, $period);

    expect($summary['billed_amount'])->toBe(608.0)
        ->and($summary['billed_amount'])->toBe($stats['billed_amount'])
        ->and($summary['paid_amount'])->toBe($stats['collected_amount'])
        ->and($summary['workflow']['outstanding_balance'])->toBe($stats['outstanding_amount'])
        ->and($metrics->periodBillingDollarStats($org->id, $period)['billed_amount'])->toBe(608.0);

    $periodKey = $period->format('Y-m');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('$608', false);

    $this->get(route('billing-claims-audit.index', ['period' => $periodKey]))
        ->assertOk()
        ->assertSee('$608', false)
        ->assertSee('1 claims/invoices', false);
});
