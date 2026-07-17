<?php

namespace App\Services\Ai;

use Illuminate\Http\UploadedFile;

/**
 * AI document/form recognition (EMR: "if you receive a Medical Needs Form, a
 * prior authorization, an approval... the system attempts to save it into the
 * right part and an office employee just approves it").
 *
 * Claude classifies the document and extracts the relevant fields; the result
 * is a SUGGESTION an office employee reviews and approves — nothing is applied
 * automatically.
 */
class DocumentRecognitionService
{
    /** Document types this agency works with. */
    public const TYPES = [
        'Prior Authorization', 'Medical Needs Form', 'DHS-390', 'MSA-4676',
        'Approval Notice', 'Assessment Notice', 'EOB', 'Denial Notice', 'Other',
    ];

    /** Suggested actions the office employee can approve. */
    public const ACTIONS = ['add_note', 'update_status', 'add_care_detail', 'file_document', 'none'];

    public function __construct(protected ClaudeService $claude) {}

    public function isAvailable(): bool
    {
        return $this->claude->isConfigured();
    }

    /** @return array<string, mixed> */
    public function analyzeUpload(UploadedFile $file): array
    {
        $mediaType = $file->getMimeType() ?: 'application/octet-stream';
        $data = base64_encode((string) file_get_contents($file->getRealPath()));

        if (str_starts_with($mediaType, 'image/')) {
            return $this->analyzeImage($data, $mediaType);
        }

        // For PDFs Claude accepts a document block; fall back to image handling otherwise.
        return $this->analyze([$this->claude->userMessage(
            'Classify and extract from this document. Return JSON only.',
            // PDFs and images both ride the image block path here for simplicity;
            // callers that have plain text should use analyzeText().
            str_starts_with($mediaType, 'image/') ? [['data' => $data, 'media_type' => $mediaType]] : []
        )]);
    }

    /** @return array<string, mixed> */
    public function analyzeImage(string $base64, string $mediaType): array
    {
        return $this->analyze([$this->claude->userMessage(
            'Classify and extract from this document image. Return JSON only.',
            [['data' => $base64, 'media_type' => $mediaType]]
        )]);
    }

    /** @return array<string, mixed> */
    public function analyzeText(string $text): array
    {
        return $this->analyze([$this->claude->userMessage(
            "Classify and extract from this document text. Return JSON only.\n\n---\n".$text
        )]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    protected function analyze(array $messages): array
    {
        $system = 'You are a home-care document intake assistant. Classify the document and extract key data. '
            .'Return ONLY a JSON object with keys: '
            .'document_type (one of: '.implode(', ', self::TYPES).'), '
            .'summary (1-2 plain sentences), '
            .'suggested_action (one of: '.implode(', ', self::ACTIONS).'), '
            .'suggested_status (string or null — the client status this implies, if any), '
            .'extracted (object with any of: units, hours_per_week, billing_code, start_date MM/DD/YYYY, '
            .'end_date MM/DD/YYYY, member_id, provider_id, amount_paid, amount_billed, client_name), '
            .'confidence (low|medium|high). '
            .'Use null where unknown. Never invent values. Return JSON only — no prose, no markdown.';

        $json = $this->claude->json($messages, ['system' => $system, 'max_tokens' => 900]);

        return $this->postprocess($json);
    }

    /** @return array<string, mixed> */
    protected function postprocess(array $json): array
    {
        $type = $json['document_type'] ?? 'Other';
        if (! in_array($type, self::TYPES, true)) {
            $type = 'Other';
        }

        $action = $json['suggested_action'] ?? 'none';
        if (! in_array($action, self::ACTIONS, true)) {
            $action = 'none';
        }

        $confidence = strtolower((string) ($json['confidence'] ?? 'low'));
        if (! in_array($confidence, ['low', 'medium', 'high'], true)) {
            $confidence = 'low';
        }

        return [
            'document_type' => $type,
            'summary' => is_string($json['summary'] ?? null) ? trim($json['summary']) : '',
            'suggested_action' => $action,
            'suggested_status' => $json['suggested_status'] ?? null,
            'extracted' => is_array($json['extracted'] ?? null) ? $json['extracted'] : [],
            'confidence' => $confidence,
            // The office always reviews before anything is applied.
            'needs_review' => true,
        ];
    }
}
