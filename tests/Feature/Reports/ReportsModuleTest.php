<?php

use App\Models\BillingClaimAudit;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('reports overview loads for admin', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Reports')
        ->assertSee('Agency overview')
        ->assertSee('Financial reports');
});

test('reports overview shows live kpis when billing data exists', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    billingClaimAuditRecord($org->id, $client->id, [
        'program_type' => BillingClaimAudit::PROGRAM_MICH,
        'total_amount' => 208920,
        'paid_amount' => 163800,
        'total_hours' => 6964,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Revenue billed')
        ->assertSee('Revenue & Collections');
});

test('featured financial report detail pages load', function (string $slug, string $heading) {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    billingClaimAuditRecord($org->id, $client->id);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('reports.show', $slug))
        ->assertOk()
        ->assertSee($heading);
})->with([
    'revenue collections' => ['revenue-collections', 'Revenue & Collections'],
    'ar aging' => ['ar-aging', 'AR Aging'],
    'margin by program' => ['margin-by-program', 'Margin by Program'],
    'payroll summary' => ['payroll-summary', 'Payroll Summary'],
    'denials' => ['denials-rejections', 'Denials & Rejections'],
    'census' => ['census-utilization', 'Census & Caregiver Utilization'],
    'compliance' => ['compliance-authorizations', 'Compliance & Authorizations'],
    'workforce' => ['workforce', 'Workforce Report'],
    'ai performance' => ['ai-agent-performance', 'AI Agent Performance'],
    'custom builder' => ['custom-builder', 'Report Builder'],
]);

test('unknown report returns 404', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get('/reports/not-a-real-report')
        ->assertNotFound();
});

test('reports export returns csv', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $response = $this->actingAsWithTwoFactor($admin)
        ->get(route('reports.export', 'revenue-collections'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

test('reports category filter works', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('reports.index', ['category' => 'operational']))
        ->assertOk()
        ->assertSee('Census & Caregiver Utilization');
});
