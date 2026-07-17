<?php

namespace App\Http\Controllers;

use App\Exceptions\Ai\ClaudeException;
use App\Models\Client;
use App\Models\Employee;
use App\Services\Ai\CaseSummaryService;
use App\Services\Ai\DocumentRecognitionService;
use App\Services\Ai\IdDocumentExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints for the Claude-powered (LLM) automations used by the Client and
 * Caregiver modules: scan-ID auto-fill, document/form recognition, and the AI
 * case-summary assistant. Every endpoint returns JSON and degrades gracefully
 * when the API key is missing or Claude errors/refuses.
 */
class AiController extends Controller
{
    public function __construct(
        protected IdDocumentExtractionService $idExtraction,
        protected DocumentRecognitionService $documentRecognition,
        protected CaseSummaryService $caseSummary,
    ) {}

    /** Scan an ID image and return identity fields for staff to confirm before saving. */
    public function scanId(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        if (! $this->idExtraction->isAvailable()) {
            return $this->unavailable();
        }

        return $this->guard(fn () => response()->json([
            'ok' => true,
            'result' => $this->idExtraction->extractFromUpload($request->file('image')),
        ]));
    }

    /** Classify + extract from a scanned/uploaded document; returns a suggestion to approve. */
    public function recognizeDocument(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required_without:text', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:20480'],
            'text' => ['required_without:file', 'nullable', 'string', 'max:50000'],
        ]);

        if (! $this->documentRecognition->isAvailable()) {
            return $this->unavailable();
        }

        return $this->guard(function () use ($request) {
            $result = $request->filled('text')
                ? $this->documentRecognition->analyzeText($request->string('text'))
                : $this->documentRecognition->analyzeUpload($request->file('file'));

            return response()->json(['ok' => true, 'result' => $result]);
        });
    }

    /** AI summary of a client case for the assistant panel / daily brief. */
    public function clientSummary($id): JsonResponse
    {
        $client = Client::withoutGlobalScopes()
            ->with(['statusRecord', 'coverageType', 'careDetails', 'statusHistories'])
            ->findOrFail($id);
        $this->authorize('view', $client);

        if (! $this->caseSummary->isAvailable()) {
            return $this->unavailable();
        }

        return $this->guard(fn () => response()->json([
            'ok' => true,
            'result' => $this->caseSummary->summarizeClient($client),
        ]));
    }

    /** AI summary of a caregiver case. */
    public function caregiverSummary($id): JsonResponse
    {
        $caregiver = Employee::withoutGlobalScopes()->with('clients.careDetails')->findOrFail($id);

        if (! $this->caseSummary->isAvailable()) {
            return $this->unavailable();
        }

        return $this->guard(fn () => response()->json([
            'ok' => true,
            'result' => $this->caseSummary->summarizeCaregiver($caregiver),
        ]));
    }

    /** Run a closure, translating ClaudeException into a clean JSON error. */
    protected function guard(\Closure $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (ClaudeException $e) {
            $status = match ($e->kind) {
                ClaudeException::RATE_LIMITED => 429,
                ClaudeException::AUTH, ClaudeException::NOT_CONFIGURED => 503,
                default => 502,
            };

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'kind' => $e->kind,
                'retryable' => $e->isRetryable(),
            ], $status);
        }
    }

    protected function unavailable(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => 'AI features are not configured. Add ANTHROPIC_API_KEY to enable scan/summary.',
            'kind' => ClaudeException::NOT_CONFIGURED,
            'retryable' => false,
        ], 503);
    }
}
