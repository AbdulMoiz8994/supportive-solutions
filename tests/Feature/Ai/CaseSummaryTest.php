<?php

use App\Services\Ai\CaseSummaryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config()->set('services.anthropic.key', 'test-key');
    config()->set('services.anthropic.model', 'claude-sonnet-4-6');
});

function fakeSummary(array $json): void
{
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode($json)]],
        'stop_reason' => 'end_turn',
        'model' => 'claude-sonnet-4-6',
        'usage' => ['input_tokens' => 150, 'output_tokens' => 60],
    ], 200)]);
}

test('summarizes a client case into summary, next action, and flags', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['status' => 'Pending']);
    $client->statusHistories()->create(['to_status' => 'Pending', 'effective_date' => now()->subDays(60)]);
    $client->load('statusHistories');

    fakeSummary([
        'summary' => 'Pending DHS client, stuck 60 days.',
        'next_action' => 'Escalate the pending application.',
        'flags' => ['Stuck > 45 days', 'No authorization on file'],
    ]);

    $r = app(CaseSummaryService::class)->summarizeClient($client);

    expect($r['summary'])->toBe('Pending DHS client, stuck 60 days.');
    expect($r['next_action'])->toBe('Escalate the pending application.');
    expect($r['flags'])->toContain('Stuck > 45 days');
});

test('client facts sent to the model include status and attention signals', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['status' => 'Pending', 'county' => 'Wayne']);
    $client->statusHistories()->create(['to_status' => 'Pending', 'effective_date' => now()->subDays(60)]);
    $client->load('statusHistories');

    fakeSummary(['summary' => 's', 'next_action' => 'n', 'flags' => []]);
    app(CaseSummaryService::class)->summarizeClient($client);

    Http::assertSent(function ($request) {
        $text = $request['messages'][0]['content'][0]['text'];
        return str_contains($text, 'Wayne')
            && str_contains($text, 'status_needs_attention');
    });
});

test('summarizes a caregiver case', function () {
    $org = $this->createOrganization();
    $caregiver = $this->createEmployee($org->id, ['champs_association_date' => null, 'has_background_check' => false]);

    fakeSummary([
        'summary' => 'Caregiver not yet CHAMPS-associated.',
        'next_action' => 'Complete CHAMPS association.',
        'flags' => ['Cannot collect hours'],
    ]);

    $r = app(CaseSummaryService::class)->summarizeCaregiver($caregiver);

    expect($r['summary'])->toBe('Caregiver not yet CHAMPS-associated.');
    expect($r['flags'])->toContain('Cannot collect hours');
});

test('drops non-string flags defensively', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);

    fakeSummary(['summary' => 's', 'next_action' => 'n', 'flags' => ['ok', 123, null, ['nested']]]);
    $r = app(CaseSummaryService::class)->summarizeClient($client);

    expect($r['flags'])->toBe(['ok']);
});

test('is unavailable without an API key', function () {
    config()->set('services.anthropic.key', null);
    expect(app(CaseSummaryService::class)->isAvailable())->toBeFalse();
});
