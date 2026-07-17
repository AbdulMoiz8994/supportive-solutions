<?php

use App\Models\BillingClaimAudit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createBillingClaimAudit(int $orgId, int $clientId, array $attributes = []): BillingClaimAudit
{
    return BillingClaimAudit::withoutGlobalScopes()->create(array_merge([
        'organization_id' => $orgId,
        'client_id' => $clientId,
        'claim_number' => '837P-TEST-'.uniqid(),
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'billing_period' => '2024-05-01',
        'period_start' => '2024-05-01',
        'period_end' => '2024-05-31',
        'total_hours' => 108.0,
        'service_code' => 'T019',
        'service_description' => 'Personal care services',
        'units' => 432,
        'hourly_rate' => 30.00,
        'total_amount' => 3240.00,
        'submission_channel' => '837P - Availity',
        'channel_subtext' => 'MCO',
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
        'status_detail' => 'Submitted',
        'audit_status' => BillingClaimAudit::AUDIT_NOT_REVIEWED,
        'submitted_at' => now()->subDays(10),
        'lifecycle_events' => [],
        'documents' => [],
    ], $attributes));
}

// --- Route access ---

test('guest is redirected to login from billing claims audit index', function () {
    $this->get(route('billing-claims-audit.index'))
        ->assertRedirect(route('signin'));
});

test('authorized admin can access billing claims audit listing', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index'))
        ->assertOk()
        ->assertSee('Billing & Claims Audit');
});

test('super administrator without organization can access billing claims audit listing', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    createBillingClaimAudit($org->id, $client->id);
    $super = $this->createUser(User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($super)
        ->get(route('billing-claims-audit.index', ['period' => '2024-05']))
        ->assertOk()
        ->assertSee('Billing & Claims Audit');
});

test('employee without permission cannot access billing claims audit', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($employee)
        ->get(route('billing-claims-audit.index'))
        ->assertForbidden();
});

test('authorized user can view a claim record', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = createBillingClaimAudit($org->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.show', $claim))
        ->assertOk()
        ->assertSee($claim->claim_number);
});

test('invalid claim id returns 404', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.show', 999999))
        ->assertNotFound();
});

test('admin cannot view claim from another organization', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $client = $this->createClient($orgA->id);
    $claim = createBillingClaimAudit($orgA->id, $client->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $this->actingAsWithTwoFactor($adminB)
        ->get(route('billing-claims-audit.show', $claim))
        ->assertNotFound();
});

// --- Listing ---

test('listing displays claim records from database', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['first_name' => 'Maria', 'last_name' => 'Hassan']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    createBillingClaimAudit($org->id, $client->id, ['claim_number' => '837P-2024-05-0001']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', ['period' => '2024-05']))
        ->assertOk()
        ->assertSee('Maria Hassan')
        ->assertSee('MICH')
        ->assertSee('$3,240.00');
});

test('empty state displays when no records exist', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', ['period' => '2024-05']))
        ->assertOk()
        ->assertSee('No billing claims found');
});

test('search by claim number works', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    createBillingClaimAudit($org->id, $client->id, ['claim_number' => '837P-UNIQUE-1234', 'total_amount' => 4500.00]);
    createBillingClaimAudit($org->id, $client->id, ['claim_number' => '837P-OTHER-5678', 'total_amount' => 1200.00]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', ['period' => '2024-05', 'search' => 'UNIQUE-1234']))
        ->assertOk()
        ->assertSee('Showing 1-1 of 1')
        ->assertSee('$4,500.00')
        ->assertDontSee('$1,200.00');
});

test('filter by claim status works', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    createBillingClaimAudit($org->id, $client->id, [
        'claim_number' => '837P-PAID-001',
        'claim_status' => BillingClaimAudit::STATUS_PAID,
        'status_detail' => 'Paid - EOB posted',
        'total_amount' => 1111.00,
    ]);
    createBillingClaimAudit($org->id, $client->id, [
        'claim_number' => '837P-HOLD-001',
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
        'status_detail' => 'On hold (CP-01)',
        'total_amount' => 2222.00,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', ['period' => '2024-05', 'status' => 'on_hold']))
        ->assertOk()
        ->assertSee('On hold (CP-01)')
        ->assertSee('$2,222.00')
        ->assertDontSee('$1,111.00');
});

test('status tab counts stay aligned with selected period rows', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['first_name' => 'Tab', 'last_name' => 'Count']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    createBillingClaimAudit($org->id, $client->id, [
        'claim_number' => '837P-2026-05-SUB',
        'billing_period' => '2026-05-01',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
        'status_detail' => 'Submitted',
    ]);
    createBillingClaimAudit($org->id, $client->id, [
        'claim_number' => '837P-2026-05-HOLD',
        'billing_period' => '2026-05-01',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
        'status_detail' => 'On hold (CP-01)',
    ]);
    createBillingClaimAudit($org->id, $client->id, [
        'claim_number' => '837P-2026-04-OLD',
        'billing_period' => '2026-04-01',
        'period_start' => '2026-04-01',
        'period_end' => '2026-04-30',
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
        'status_detail' => 'Submitted',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', ['period' => '2026-05']))
        ->assertOk()
        ->assertSee('All 2')
        ->assertSee('Submitted 1')
        ->assertSee('On hold (CP-01) 1');
});

test('status tab counts include records with mid month billing period dates', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    createBillingClaimAudit($org->id, $client->id, [
        'claim_number' => '837P-2026-05-MID',
        'billing_period' => '2026-05-15',
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', ['period' => '2026-05']))
        ->assertOk()
        ->assertSee('All 1')
        ->assertSee('Submitted 1');
});

test('status tab counts respect program and search filters', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['first_name' => 'Filter', 'last_name' => 'Target']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    createBillingClaimAudit($org->id, $client->id, [
        'claim_number' => '837P-MICH-SUB',
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
    ]);
    createBillingClaimAudit($org->id, $client->id, [
        'claim_number' => 'HH-DHS-HOLD',
        'program_type' => BillingClaimAudit::PROGRAM_DHS,
        'submission_channel' => 'Home Help - Sigma Portal',
        'claim_status' => BillingClaimAudit::STATUS_ON_HOLD,
        'status_detail' => 'On hold (CP-01)',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', ['period' => '2024-05', 'program' => 'DHS']))
        ->assertOk()
        ->assertSee('All 1')
        ->assertSee('On hold (CP-01) 1')
        ->assertDontSee('Submitted 1');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', ['period' => '2024-05', 'search' => '837P-MICH-SUB']))
        ->assertOk()
        ->assertSee('All 1')
        ->assertSee('Submitted 1');
});

test('status tab counts derive from billing status when claim status is unset', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    createBillingClaimAudit($org->id, $client->id, [
        'claim_number' => '837P-PAID-VIA-BILLING',
        'claim_status' => 'legacy_unset',
        'billing_status' => BillingClaimAudit::BILLING_PAID,
        'status_detail' => 'Paid',
    ]);
    createBillingClaimAudit($org->id, $client->id, [
        'claim_number' => '837P-SUB-VIA-BILLING',
        'claim_status' => 'legacy_unset',
        'billing_status' => BillingClaimAudit::BILLING_SUBMITTED,
        'status_detail' => 'Submitted',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', ['period' => '2024-05']))
        ->assertOk()
        ->assertSee('All 2')
        ->assertSee('Paid / confirmed 1')
        ->assertSee('Submitted 1');
});

test('filter by claim status matches effective status from billing status', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $paidClaim = createBillingClaimAudit($org->id, $client->id, [
        'claim_number' => '837P-PAID-EFFECTIVE',
        'claim_status' => 'legacy_unset',
        'billing_status' => BillingClaimAudit::BILLING_PAID,
        'total_amount' => 5000.00,
    ]);
    createBillingClaimAudit($org->id, $client->id, [
        'claim_number' => '837P-SUB-OTHER',
        'claim_status' => BillingClaimAudit::STATUS_SUBMITTED,
        'total_amount' => 1000.00,
    ]);

    expect($paidClaim->fresh()->effectiveClaimStatus())->toBe(BillingClaimAudit::STATUS_PAID);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', ['period' => '2024-05', 'status' => 'paid']))
        ->assertOk()
        ->assertSee('Showing 1-1 of 1')
        ->assertSee('$5,000.00')
        ->assertDontSee('$1,000.00');
});

test('filter by program works', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    createBillingClaimAudit($org->id, $client->id, ['claim_number' => '837P-MICH-001', 'program_type' => 'MICH']);
    createBillingClaimAudit($org->id, $client->id, [
        'claim_number' => 'HH-DHS-001',
        'program_type' => 'DHS',
        'submission_channel' => 'Home Help - Sigma Portal',
        'total_amount' => 2484.00,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', ['period' => '2024-05', 'program' => 'DHS']))
        ->assertOk()
        ->assertSee('Home Help - Sigma Portal')
        ->assertSee('$2,484.00')
        ->assertSee('Showing 1-1 of 1');
});

test('long claim number does not break listing layout', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $longNumber = '837P-'.str_repeat('X', 80);
    createBillingClaimAudit($org->id, $client->id, ['claim_number' => $longNumber]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', ['period' => '2024-05']))
        ->assertOk()
        ->assertSee('Billing & Claims Audit');
});

// --- Update rate ---

test('authorized user can update billing rate and amount recalculates', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = createBillingClaimAudit($org->id, $client->id, [
        'total_hours' => 100,
        'hourly_rate' => 30.00,
        'total_amount' => 3000.00,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->patch(route('billing-claims-audit.update-rate', $claim), ['hourly_rate' => 35.00])
        ->assertRedirect(route('billing-claims-audit.show', $claim));

    $claim->refresh();
    expect((float) $claim->hourly_rate)->toBe(35.0)
        ->and((float) $claim->total_amount)->toBe(3500.0);
});

test('operations staff without edit permission cannot update rate', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);
    $claim = createBillingClaimAudit($org->id, $client->id);

    $this->actingAsWithTwoFactor($staff)
        ->patch(route('billing-claims-audit.update-rate', $claim), ['hourly_rate' => 35.00])
        ->assertForbidden();
});

test('negative hourly rate is rejected', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = createBillingClaimAudit($org->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->patch(route('billing-claims-audit.update-rate', $claim), ['hourly_rate' => -10])
        ->assertSessionHasErrors('hourly_rate');

    expect((float) $claim->fresh()->hourly_rate)->toBe(30.0);
});

test('extremely large hourly rate is rejected', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = createBillingClaimAudit($org->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->patch(route('billing-claims-audit.update-rate', $claim), ['hourly_rate' => 999999])
        ->assertSessionHasErrors('hourly_rate');
});

test('invalid audit status is rejected on update', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = createBillingClaimAudit($org->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('billing-claims-audit.update', $claim), ['audit_status' => 'hacked_status'])
        ->assertSessionHasErrors('audit_status');
});

test('valid audit status update succeeds', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = createBillingClaimAudit($org->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('billing-claims-audit.update', $claim), [
            'audit_status' => BillingClaimAudit::AUDIT_IN_REVIEW,
            'notes' => 'Reviewing EOB discrepancy',
        ])
        ->assertRedirect(route('billing-claims-audit.show', $claim));

    expect($claim->fresh()->audit_status)->toBe(BillingClaimAudit::AUDIT_IN_REVIEW);
});

// --- Security ---

test('xss payload in notes is escaped on show page', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $payload = '<script>alert("xss")</script>';
    $claim = createBillingClaimAudit($org->id, $client->id, ['notes' => $payload]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('billing-claims-audit.update', $claim), [
            'audit_status' => BillingClaimAudit::AUDIT_ISSUE_FOUND,
            'notes' => $payload,
        ]);

    $claim->refresh();
    expect($claim->notes)->toBe($payload);

    $response = $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.show', $claim));

    $response->assertOk();
    expect($response->getContent())->not->toContain('<script>alert("xss")</script>');
});

test('sql injection-like search input does not cause errors', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index', [
            'period' => '2024-05',
            'search' => "' OR 1=1; DROP TABLE billing_claim_audits; --",
        ]))
        ->assertOk();
});

test('mass assignment cannot update protected organization id', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $client = $this->createClient($orgA->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);
    $claim = createBillingClaimAudit($orgA->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('billing-claims-audit.update', $claim), [
            'audit_status' => BillingClaimAudit::AUDIT_IN_REVIEW,
            'organization_id' => $orgB->id,
        ]);

    expect($claim->fresh()->organization_id)->toBe($orgA->id);
});

test('soft deleted claim returns 404', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $claim = createBillingClaimAudit($org->id, $client->id);
    $claim->delete();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.show', $claim))
        ->assertNotFound();
});

// --- Aging ---

test('aging report page loads with dynamic bucket data', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    createBillingClaimAudit($org->id, $client->id, [
        'claim_status' => BillingClaimAudit::STATUS_AWAITING_PAYMENT,
        'submitted_at' => now()->subDays(45),
        'total_amount' => 5000,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.aging', ['period' => now()->format('Y-m')]))
        ->assertOk()
        ->assertSee('Aging report')
        ->assertSee('Total outstanding');
});

test('export returns csv download', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    createBillingClaimAudit($org->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.export', ['period' => '2024-05']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('legacy billing route is disabled in favor of billing claims audit', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing.index'))
        ->assertNotFound();

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.index'))
        ->assertOk();
});

test('aging export returns csv download', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    createBillingClaimAudit($org->id, $client->id, [
        'claim_status' => BillingClaimAudit::STATUS_AWAITING_PAYMENT,
        'billing_status' => BillingClaimAudit::BILLING_PENDING_PAYMENT,
        'submitted_at' => now()->subDays(45),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.aging.export', ['period' => now()->format('Y-m')]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload();
});

test('billing claim pdf download returns file when pdf exists on disk', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $relative = 'billing-claims-audit/test-'.uniqid().'.pdf';
    $fullPath = storage_path('app/'.$relative);

    if (! is_dir(dirname($fullPath))) {
        mkdir(dirname($fullPath), 0755, true);
    }

    file_put_contents($fullPath, '%PDF-1.4 test');

    $claim = createBillingClaimAudit($org->id, $client->id, ['pdf_path' => $relative]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('billing-claims-audit.pdf.download', $claim))
        ->assertOk()
        ->assertDownload(basename($relative));

    @unlink($fullPath);
});
