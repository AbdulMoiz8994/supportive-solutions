<?php

use App\Services\Communication\CommunicationIntegrationStatusService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('compose integration status reports unavailable channels when credentials are missing', function () {
    $status = app(CommunicationIntegrationStatusService::class)->forCompose();

    expect($status['ringcentral'])->toBeFalse()
        ->and($status['google'])->toBeFalse()
        ->and($status['ringcentral_message'])->not->toBe('')
        ->and($status['google_message'])->not->toBe('');
});

test('compose integration readiness helpers mirror for compose payload', function () {
    $service = app(CommunicationIntegrationStatusService::class);

    expect($service->ringCentralReady())->toBe($service->forCompose()['ringcentral'])
        ->and($service->googleReady())->toBe($service->forCompose()['google']);
});
