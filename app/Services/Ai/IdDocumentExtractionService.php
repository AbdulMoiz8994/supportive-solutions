<?php

namespace App\Services\Ai;

use Illuminate\Http\UploadedFile;

/**
 * Scan-ID auto-fill (EMR "scan the ID and it pulls the picture, reads name,
 * address, DOB and inputs this automatically; before saving, confirm").
 *
 * Sends the ID image to Claude (vision) and returns normalized identity fields
 * for the staff to review in a confirmation popup before they are written to a
 * client or caregiver record. LLM reads; deterministic code normalizes.
 */
class IdDocumentExtractionService
{
    /** Fields we attempt to read off a government-issued ID. */
    public const FIELDS = [
        'first_name', 'last_name', 'middle_name', 'date_of_birth', 'sex',
        'address', 'city', 'state', 'zip', 'id_number', 'document_type', 'expiration_date',
    ];

    public function __construct(protected ClaudeService $claude) {}

    public function isAvailable(): bool
    {
        return $this->claude->isConfigured();
    }

    /**
     * @return array{fields: array<string, mixed>, needs_confirmation: bool, missing: array<int, string>, model: string}
     */
    public function extractFromUpload(UploadedFile $file): array
    {
        $mediaType = $file->getMimeType() ?: 'image/jpeg';
        $data = base64_encode((string) file_get_contents($file->getRealPath()));

        return $this->extract($data, $mediaType);
    }

    /**
     * @return array{fields: array<string, mixed>, needs_confirmation: bool, missing: array<int, string>, model: string}
     */
    public function extract(string $base64, string $mediaType): array
    {
        $system = 'You are an OCR extraction assistant for a home-care agency. '
            .'Read the government-issued ID in the image and return ONLY a JSON object with these keys: '
            .'first_name, last_name, middle_name, date_of_birth (MM/DD/YYYY), sex (M/F), address (street line only), '
            .'city, state (2-letter USPS code), zip, id_number, document_type, expiration_date (MM/DD/YYYY). '
            .'Use null for any field you cannot read with confidence — do not guess. '
            .'Return JSON only: no prose, no markdown, no code fences.';

        $messages = [$this->claude->userMessage(
            'Extract the identity fields from this ID image. Return JSON only.',
            [['data' => $base64, 'media_type' => $mediaType]]
        )];

        $json = $this->claude->json($messages, ['system' => $system, 'max_tokens' => 700]);
        $fields = $this->normalize($json);

        $missing = array_values(array_filter(
            ['first_name', 'last_name', 'date_of_birth', 'address'],
            fn ($k) => empty($fields[$k])
        ));

        return [
            'fields' => $fields,
            // Per the EMR spec, the read is ALWAYS confirmed by staff before saving.
            'needs_confirmation' => true,
            'missing' => $missing,
            'model' => (string) ($json['_model'] ?? $this->claude->model()),
        ];
    }

    /** @return array<string, mixed> */
    protected function normalize(array $json): array
    {
        $out = [];
        foreach (self::FIELDS as $key) {
            $value = $json[$key] ?? null;
            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }
            $out[$key] = $value;
        }

        if (! empty($out['state']) && is_string($out['state'])) {
            $out['state'] = strtoupper(substr($out['state'], 0, 2));
        }
        if (! empty($out['sex']) && is_string($out['sex'])) {
            $out['sex'] = strtoupper(substr($out['sex'], 0, 1));
        }

        // A full formatted address line for the single-field client form.
        $out['address_full'] = collect([$out['address'], $out['city'], $out['state'], $out['zip']])
            ->filter()
            ->implode(', ') ?: null;

        return $out;
    }
}
