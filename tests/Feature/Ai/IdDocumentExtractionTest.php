<?php

use App\Exceptions\Ai\ClaudeException;
use App\Services\Ai\IdDocumentExtractionService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.anthropic.key', 'test-key');
    config()->set('services.anthropic.model', 'claude-sonnet-4-6');
});

function fakeId(string $text, int $status = 200, string $stop = 'end_turn'): void
{
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => $text]],
        'stop_reason' => $stop,
        'model' => 'claude-sonnet-4-6',
        'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
    ], $status)]);
}

test('is unavailable without an API key', function () {
    config()->set('services.anthropic.key', null);
    expect(app(IdDocumentExtractionService::class)->isAvailable())->toBeFalse();
});

test('extracts and normalizes identity fields from an ID', function () {
    fakeId(json_encode([
        'first_name' => '  John ', 'last_name' => 'Dutton', 'middle_name' => null,
        'date_of_birth' => '05/18/1943', 'sex' => 'male', 'address' => '220 Bagley Ave',
        'city' => 'Detroit', 'state' => 'michigan', 'zip' => '48226',
        'id_number' => 'D-123-456', 'document_type' => 'Driver License', 'expiration_date' => '05/18/2028',
    ]));

    $r = app(IdDocumentExtractionService::class)->extract('BASE64', 'image/jpeg');

    expect($r['fields']['first_name'])->toBe('John');        // trimmed
    expect($r['fields']['state'])->toBe('MI');               // normalized to 2-letter upper
    expect($r['fields']['sex'])->toBe('M');                  // normalized to single upper
    expect($r['fields']['address_full'])->toBe('220 Bagley Ave, Detroit, MI, 48226');
    expect($r['needs_confirmation'])->toBeTrue();            // always confirmed before save
    expect($r['missing'])->toBe([]);
});

test('reports missing critical fields the model could not read', function () {
    fakeId(json_encode([
        'first_name' => 'Jane', 'last_name' => null, 'date_of_birth' => null, 'address' => null,
    ]));

    $r = app(IdDocumentExtractionService::class)->extract('BASE64', 'image/jpeg');

    expect($r['fields']['first_name'])->toBe('Jane');
    expect($r['missing'])->toContain('last_name');
    expect($r['missing'])->toContain('date_of_birth');
    expect($r['missing'])->toContain('address');
});

test('sends the ID image as a base64 image block', function () {
    fakeId('{"first_name":"X"}');
    app(IdDocumentExtractionService::class)->extract('THEBYTES', 'image/png');
    Http::assertSent(function ($request) {
        $block = $request['messages'][0]['content'][0];
        return $block['type'] === 'image'
            && $block['source']['media_type'] === 'image/png'
            && $block['source']['data'] === 'THEBYTES';
    });
});

test('propagates a refusal as an exception', function () {
    fakeId('', 200, 'refusal');
    expect(fn () => app(IdDocumentExtractionService::class)->extract('B', 'image/jpeg'))
        ->toThrow(ClaudeException::class);
});

test('throws when the model returns unparseable output', function () {
    fakeId('Sorry, the image is too blurry to read.');
    expect(fn () => app(IdDocumentExtractionService::class)->extract('B', 'image/jpeg'))
        ->toThrow(ClaudeException::class);
});
