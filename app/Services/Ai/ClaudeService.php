<?php

namespace App\Services\Ai;

use App\Exceptions\Ai\ClaudeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the Anthropic Claude Messages API.
 *
 * Uses Laravel's Http facade (raw HTTP) — matching the existing OpenAI
 * integration in CommunicationAiSummaryService and making every call testable
 * with Http::fake(). Model defaults to config('services.anthropic.model')
 * (Sonnet 4.6 — balances cost and quality) and is overridable per call.
 */
class ClaudeService
{
    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    public function model(): string
    {
        return (string) config('services.anthropic.model', 'claude-sonnet-4-6');
    }

    /**
     * Low-level Messages API call.
     *
     * @param  array<int, array<string, mixed>>  $messages  Anthropic messages array.
     * @param  array<string, mixed>  $options  system, model, max_tokens.
     * @return array{text: string, json: array<string, mixed>|null, stop_reason: ?string, refused: bool, usage: array<string, mixed>, model: string}
     *
     * @throws ClaudeException
     */
    public function message(array $messages, array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw ClaudeException::notConfigured();
        }

        $payload = [
            'model' => $options['model'] ?? $this->model(),
            'max_tokens' => $options['max_tokens'] ?? 1024,
            'messages' => $messages,
        ];
        if (! empty($options['system'])) {
            $payload['system'] = $options['system'];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => config('services.anthropic.version', '2023-06-01'),
            ])
                ->acceptJson()
                ->timeout((int) config('services.anthropic.timeout', 60))
                ->post(rtrim((string) config('services.anthropic.base_url', 'https://api.anthropic.com'), '/').'/v1/messages', $payload);
        } catch (ConnectionException $e) {
            throw ClaudeException::connection($e->getMessage());
        }

        if ($response->status() === 401) {
            throw ClaudeException::auth();
        }
        if ($response->status() === 429) {
            throw ClaudeException::rateLimited();
        }
        if (! $response->successful()) {
            throw ClaudeException::http($response->status(), (string) $response->body());
        }

        $data = $response->json() ?? [];
        $stop = $data['stop_reason'] ?? null;
        $text = $this->extractText($data);

        return [
            'text' => $text,
            'json' => $this->tryParseJson($text),
            'stop_reason' => $stop,
            'refused' => $stop === 'refusal',
            'usage' => $data['usage'] ?? [],
            'model' => $data['model'] ?? $payload['model'],
        ];
    }

    /**
     * Call Claude and require a JSON object back. Throws on refusal / unparseable output.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws ClaudeException
     */
    public function json(array $messages, array $options = []): array
    {
        $result = $this->message($messages, $options);

        if ($result['refused']) {
            throw new ClaudeException(ClaudeException::HTTP, 'Claude declined this request (safety refusal).');
        }
        if (! is_array($result['json'])) {
            throw ClaudeException::emptyResponse();
        }

        return $result['json'];
    }

    /** Build a user message from text plus optional base64 images (images first, per API guidance). */
    public function userMessage(string $text, array $images = []): array
    {
        $content = [];
        foreach ($images as $image) {
            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $image['media_type'] ?? 'image/jpeg',
                    'data' => $image['data'],
                ],
            ];
        }
        $content[] = ['type' => 'text', 'text' => $text];

        return ['role' => 'user', 'content' => $content];
    }

    /** Concatenate all text blocks from a Messages API response. */
    protected function extractText(array $data): string
    {
        $out = '';
        foreach (($data['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $out .= $block['text'] ?? '';
            }
        }

        return trim($out);
    }

    /** Best-effort JSON parse — tolerates ```json fences and surrounding prose. */
    protected function tryParseJson(string $text): ?array
    {
        $t = trim($text);
        if ($t === '') {
            return null;
        }

        // Strip a leading/trailing markdown code fence if present.
        if (str_starts_with($t, '```')) {
            $t = (string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $t);
        }

        $decoded = json_decode($t, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Fall back to the first {...} object embedded in prose.
        if (preg_match('/\{.*\}/s', $t, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
