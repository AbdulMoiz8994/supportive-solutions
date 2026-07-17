<?php

use App\Exceptions\Ai\ClaudeException;
use App\Services\Ai\ClaudeService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.anthropic.key', 'test-key');
    config()->set('services.anthropic.model', 'claude-sonnet-4-6');
    config()->set('services.anthropic.base_url', 'https://api.anthropic.com');
});

/** Fake a Claude Messages API response carrying a single text block. */
function fakeClaude(string $text, int $status = 200, string $stop = 'end_turn'): void
{
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => $text]],
        'stop_reason' => $stop,
        'model' => 'claude-sonnet-4-6',
        'usage' => ['input_tokens' => 12, 'output_tokens' => 8],
    ], $status)]);
}

function expectKind(callable $fn, string $kind): void
{
    $caught = null;
    try {
        $fn();
    } catch (ClaudeException $e) {
        $caught = $e;
    }
    expect($caught)->not->toBeNull();
    expect($caught->kind)->toBe($kind);
}

test('throws not_configured when no API key is set', function () {
    config()->set('services.anthropic.key', null);
    expect(app(ClaudeService::class)->isConfigured())->toBeFalse();
    expectKind(fn () => app(ClaudeService::class)->message([['role' => 'user', 'content' => 'hi']]), ClaudeException::NOT_CONFIGURED);
});

test('returns text and parsed json on success', function () {
    fakeClaude('{"first_name":"Jane","ok":true}');
    $r = app(ClaudeService::class)->message([['role' => 'user', 'content' => 'hi']]);
    expect($r['text'])->toBe('{"first_name":"Jane","ok":true}');
    expect($r['json'])->toMatchArray(['first_name' => 'Jane', 'ok' => true]);
    expect($r['refused'])->toBeFalse();
});

test('sends the API key, version header, and model in the request', function () {
    fakeClaude('{}');
    app(ClaudeService::class)->message([['role' => 'user', 'content' => 'hi']], ['system' => 'sys']);
    Http::assertSent(function ($request) {
        return $request->hasHeader('x-api-key', 'test-key')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $request['model'] === 'claude-sonnet-4-6'
            && $request['system'] === 'sys'
            && str_ends_with($request->url(), '/v1/messages');
    });
});

test('parses json wrapped in markdown code fences', function () {
    fakeClaude("```json\n{\"a\":1}\n```");
    $r = app(ClaudeService::class)->message([['role' => 'user', 'content' => 'x']]);
    expect($r['json'])->toMatchArray(['a' => 1]);
});

test('parses a json object embedded in surrounding prose', function () {
    fakeClaude('Here is the data: {"a":2} — hope that helps.');
    $r = app(ClaudeService::class)->message([['role' => 'user', 'content' => 'x']]);
    expect($r['json'])->toMatchArray(['a' => 2]);
});

test('returns null json when the response is not json', function () {
    fakeClaude('I could not read that.');
    $r = app(ClaudeService::class)->message([['role' => 'user', 'content' => 'x']]);
    expect($r['json'])->toBeNull();
    expect($r['text'])->toBe('I could not read that.');
});

test('marks a safety refusal', function () {
    fakeClaude('', 200, 'refusal');
    $r = app(ClaudeService::class)->message([['role' => 'user', 'content' => 'x']]);
    expect($r['refused'])->toBeTrue();
});

test('throws auth on 401', function () {
    fakeClaude('{"error":"bad key"}', 401);
    expectKind(fn () => app(ClaudeService::class)->message([['role' => 'user', 'content' => 'x']]), ClaudeException::AUTH);
});

test('throws rate_limited on 429', function () {
    fakeClaude('{"error":"slow down"}', 429);
    expectKind(fn () => app(ClaudeService::class)->message([['role' => 'user', 'content' => 'x']]), ClaudeException::RATE_LIMITED);
});

test('throws http on 500 and reports it retryable', function () {
    fakeClaude('boom', 500);
    $caught = null;
    try {
        app(ClaudeService::class)->message([['role' => 'user', 'content' => 'x']]);
    } catch (ClaudeException $e) {
        $caught = $e;
    }
    expect($caught)->not->toBeNull();
    expect($caught->kind)->toBe(ClaudeException::HTTP);
    expect($caught->isRetryable())->toBeTrue();
});

test('json() throws empty_response when the model returns non-json', function () {
    fakeClaude('not json at all');
    expectKind(fn () => app(ClaudeService::class)->json([['role' => 'user', 'content' => 'x']]), ClaudeException::EMPTY_RESPONSE);
});

test('json() throws on a refusal', function () {
    fakeClaude('', 200, 'refusal');
    expectKind(fn () => app(ClaudeService::class)->json([['role' => 'user', 'content' => 'x']]), ClaudeException::HTTP);
});

test('userMessage places images before the text block', function () {
    $msg = app(ClaudeService::class)->userMessage('describe', [['data' => 'BASE64', 'media_type' => 'image/png']]);
    expect($msg['role'])->toBe('user');
    expect($msg['content'][0]['type'])->toBe('image');
    expect($msg['content'][0]['source']['data'])->toBe('BASE64');
    expect($msg['content'][1]['type'])->toBe('text');
});
