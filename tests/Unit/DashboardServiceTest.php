<?php

use App\Models\Billing;
use App\Models\Client;
use App\Models\Organization;
use App\Services\DashboardService;
use App\Services\OrganizationMetricsService;

test('organization metrics service returns zeroed cards for missing organization', function () {
    $cards = app(OrganizationMetricsService::class)->getStatCards(null);

    expect($cards)->toHaveCount(5)
        ->and($cards[0]['value'])->toBe('0')
        ->and($cards[2]['value'])->toBe('$0');
});

test('dashboard service builds monthly chart with twelve entries', function () {
    $org = Organization::create(['name' => 'Chart Org', 'status' => 'Active']);

    Client::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'first_name' => 'A',
        'last_name' => 'B',
        'status' => 'Active',
    ]);

    $clientId = Client::withoutGlobalScopes()->first()->id;

    Billing::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'client_id' => $clientId,
        'invoice_number' => 'INV-2001',
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'total_amount' => 1200,
        'status' => 'Paid',
    ]);

    $dashboard = app(DashboardService::class)->build($org->id);

    expect($dashboard['monthlyChart'])->toHaveCount(12)
        ->and($dashboard['statCards'][0]['title'])->toBe('Active Clients')
        ->and($dashboard['organizations'][0]['name'])->toBe('Chart Org');
});
