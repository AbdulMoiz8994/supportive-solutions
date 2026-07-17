<?php

use App\Models\GlobalIntegrationHealth;
use Illuminate\Support\Carbon;

test('status label and badge class map each health status', function () {
    $connected = new GlobalIntegrationHealth(['status' => GlobalIntegrationHealth::STATUS_CONNECTED]);
    $partial = new GlobalIntegrationHealth(['status' => GlobalIntegrationHealth::STATUS_PARTIAL]);
    $error = new GlobalIntegrationHealth(['status' => GlobalIntegrationHealth::STATUS_ERROR]);
    $missing = new GlobalIntegrationHealth(['status' => GlobalIntegrationHealth::STATUS_NOT_CONFIGURED]);

    expect($connected->statusLabel())->toBe('Connected')
        ->and($partial->statusLabel())->toBe('Partial')
        ->and($error->statusLabel())->toBe('Error')
        ->and($missing->statusLabel())->toBe('Not configured')
        ->and($connected->statusBadgeClass())->toContain('emerald')
        ->and($partial->statusBadgeClass())->toContain('amber')
        ->and($error->statusBadgeClass())->toContain('red')
        ->and($missing->statusBadgeClass())->toContain('slate');
});

test('checks recommendation and detail message read from stored details', function () {
    $health = new GlobalIntegrationHealth([
        'message' => '2/3 checks passed · 88ms',
        'latency_ms' => 88,
        'details' => [
            'message' => 'RingCentral OAuth failed.',
            'summary' => '2/3 checks passed · 88ms',
            'recommendation' => 'Verify JWT or client credentials.',
            'checks' => [
                ['name' => 'Vault credentials', 'passed' => true, 'detail' => 'Configured'],
                ['name' => 'OAuth + extension API', 'passed' => false, 'detail' => 'HTTP 401'],
            ],
        ],
    ]);

    expect($health->checks())->toHaveCount(2)
        ->and($health->recommendation())->toBe('Verify JWT or client credentials.')
        ->and($health->detailMessage())->toBe('RingCentral OAuth failed.');
});

test('display status includes label timestamp latency and headline', function () {
    $health = new GlobalIntegrationHealth([
        'status' => GlobalIntegrationHealth::STATUS_CONNECTED,
        'message' => '3/3 checks passed · 120ms',
        'latency_ms' => 120,
        'last_tested_at' => Carbon::parse('2026-06-25 14:30:00'),
        'details' => [
            'message' => 'AccountantsWorld credentials and API endpoint verified.',
        ],
    ]);

    expect($health->displayStatus())->toBe(
        'Connected · Jun 25 2:30pm · 120ms · AccountantsWorld credentials and API endpoint verified.'
    );
});

test('display status returns headline when never tested', function () {
    $health = new GlobalIntegrationHealth([
        'status' => GlobalIntegrationHealth::STATUS_NOT_CONFIGURED,
        'message' => 'Not tested yet',
    ]);

    expect($health->displayStatus())->toBe('Not tested yet');
});
