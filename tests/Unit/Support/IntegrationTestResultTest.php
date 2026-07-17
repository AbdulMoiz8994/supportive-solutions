<?php

use App\Models\GlobalIntegrationHealth;
use App\Support\IntegrationTestResult;

test('passed marks result as connected with success', function () {
    $result = IntegrationTestResult::make(false, GlobalIntegrationHealth::STATUS_ERROR, 'pending')
        ->check('OAuth', true, 'Token acquired', 42)
        ->passed('All checks passed');

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(GlobalIntegrationHealth::STATUS_CONNECTED)
        ->and($result->message)->toBe('All checks passed')
        ->and($result->passedChecks())->toBe(1)
        ->and($result->totalChecks())->toBe(1);
});

test('not configured and partial set the expected statuses', function () {
    $missing = IntegrationTestResult::make(false, GlobalIntegrationHealth::STATUS_ERROR, 'x')
        ->notConfigured('Credentials missing');

    $partial = IntegrationTestResult::make(false, GlobalIntegrationHealth::STATUS_ERROR, 'x')
        ->check('Portal login', true, 'Username stored')
        ->check('Portal endpoint', false, 'Unreachable')
        ->partial('1/2 portal checks passed');

    expect($missing->status)->toBe(GlobalIntegrationHealth::STATUS_NOT_CONFIGURED)
        ->and($missing->success)->toBeFalse()
        ->and($partial->status)->toBe(GlobalIntegrationHealth::STATUS_PARTIAL)
        ->and($partial->success)->toBeFalse();
});

test('to array builds a check summary when checks exist', function () {
    $result = IntegrationTestResult::make(false, GlobalIntegrationHealth::STATUS_ERROR, 'ignored', 'api')
        ->check('API key', true, 'Stored')
        ->check('Endpoint', false, 'HTTP 500')
        ->recommend('Verify API URL in Credential Vault.')
        ->failed(GlobalIntegrationHealth::STATUS_ERROR, 'Endpoint failed');

    $payload = $result->toArray();

    expect($payload)->toMatchArray([
        'success' => false,
        'status' => GlobalIntegrationHealth::STATUS_ERROR,
        'message' => 'Endpoint failed',
        'method' => 'api',
        'recommendation' => 'Verify API URL in Credential Vault.',
    ])
        ->and($payload['checks'])->toHaveCount(2)
        ->and($payload['summary'])->toMatch('/1\/2 checks passed · \d+ms/');
});

test('to array falls back to message when no checks were recorded', function () {
    $payload = IntegrationTestResult::make(
        false,
        GlobalIntegrationHealth::STATUS_NOT_CONFIGURED,
        'No credential mapping exists for this integration.',
    )->toArray();

    expect($payload['summary'])->toBe('No credential mapping exists for this integration.')
        ->and($payload['checks'])->toBe([]);
});

test('from array restores checks recommendation and status', function () {
    $original = IntegrationTestResult::make(false, GlobalIntegrationHealth::STATUS_ERROR, 'x', 'rpa')
        ->check('Portal login', true, 'Username stored', 12)
        ->recommend('Open Credential Vault.')
        ->passed('Ready');

    $restored = IntegrationTestResult::fromArray($original->toArray());

    expect($restored->success)->toBeTrue()
        ->and($restored->status)->toBe(GlobalIntegrationHealth::STATUS_CONNECTED)
        ->and($restored->method)->toBe('rpa')
        ->and($restored->recommendation)->toBe('Open Credential Vault.')
        ->and($restored->checks())->toBe($original->checks());
});
