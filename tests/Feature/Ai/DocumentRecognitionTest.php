<?php

use App\Services\Ai\DocumentRecognitionService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.anthropic.key', 'test-key');
    config()->set('services.anthropic.model', 'claude-sonnet-4-6');
});

function fakeDoc(string $text, int $status = 200, string $stop = 'end_turn'): void
{
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => $text]],
        'stop_reason' => $stop,
        'model' => 'claude-sonnet-4-6',
        'usage' => ['input_tokens' => 200, 'output_tokens' => 80],
    ], $status)]);
}

test('classifies a prior authorization and extracts its fields', function () {
    fakeDoc(json_encode([
        'document_type' => 'Prior Authorization',
        'summary' => 'Aetna PA for 56 units of T1019, valid 6 months.',
        'suggested_action' => 'add_care_detail',
        'suggested_status' => 'Active',
        'extracted' => ['units' => 56, 'billing_code' => 'T1019', 'start_date' => '01/01/2026', 'end_date' => '07/01/2026'],
        'confidence' => 'high',
    ]));

    $r = app(DocumentRecognitionService::class)->analyzeText('...PA letter text...');

    expect($r['document_type'])->toBe('Prior Authorization');
    expect($r['suggested_action'])->toBe('add_care_detail');
    expect($r['extracted']['units'])->toBe(56);
    expect($r['confidence'])->toBe('high');
    expect($r['needs_review'])->toBeTrue();   // office must approve
});

test('coerces an unknown document type to Other', function () {
    fakeDoc(json_encode(['document_type' => 'Random Fax', 'summary' => 'x', 'suggested_action' => 'add_note', 'confidence' => 'medium']));
    $r = app(DocumentRecognitionService::class)->analyzeText('text');
    expect($r['document_type'])->toBe('Other');
});

test('coerces an unknown action and confidence to safe defaults', function () {
    fakeDoc(json_encode(['document_type' => 'EOB', 'suggested_action' => 'delete_everything', 'confidence' => 'definitely']));
    $r = app(DocumentRecognitionService::class)->analyzeText('text');
    expect($r['suggested_action'])->toBe('none');
    expect($r['confidence'])->toBe('low');
    expect($r['extracted'])->toBe([]);   // missing extracted → empty array, never null
});

test('sends document text in the user message', function () {
    fakeDoc(json_encode(['document_type' => 'Other', 'summary' => 's', 'suggested_action' => 'none', 'confidence' => 'low']));
    app(DocumentRecognitionService::class)->analyzeText('UNIQUE-NEEDLE-123');
    Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'][0]['text'], 'UNIQUE-NEEDLE-123'));
});

test('analyzeImage sends a base64 image block', function () {
    fakeDoc(json_encode(['document_type' => 'Medical Needs Form', 'summary' => 's', 'suggested_action' => 'file_document', 'confidence' => 'medium']));
    $r = app(DocumentRecognitionService::class)->analyzeImage('IMGBYTES', 'image/png');
    expect($r['document_type'])->toBe('Medical Needs Form');
    Http::assertSent(fn ($request) => $request['messages'][0]['content'][0]['type'] === 'image');
});
