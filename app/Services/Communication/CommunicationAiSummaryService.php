<?php

namespace App\Services\Communication;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CommunicationAiSummaryService
{
    /**
     * Generate an AI summary for a communication body.
     */
    public function summarize(
        string $body,
        ?string $subject = null,
        string $channel = 'message',
        string $direction = 'inbound',
        ?string $partyName = null,
    ): string {
        $body = trim($body);
        $subject = trim((string) $subject);

        if ($body === '' && $subject === '') {
            return 'No message content.';
        }

        $driver = config('communications.ai.driver', 'local');

        if ($driver === 'openai' && filled(config('communications.ai.openai_api_key'))) {
            $ai = $this->summarizeWithOpenAi($body, $subject, $channel, $direction, $partyName);

            if ($ai) {
                return $ai;
            }
        }

        return $this->summarizeLocally($body, $subject, $channel, $direction, $partyName);
    }

    protected function summarizeLocally(
        string $body,
        string $subject,
        string $channel,
        string $direction,
        ?string $partyName,
    ): string {
        $text = $body !== '' ? $body : $subject;
        $text = preg_replace('/\s+/', ' ', strip_tags($text)) ?? '';
        $text = trim($text);

        $party = $partyName ? "{$partyName}: " : '';
        $prefix = match ($direction) {
            'outbound' => 'Outbound '.ucfirst($channel).' — ',
            'inbound' => 'Inbound '.ucfirst($channel).' — ',
            default => '',
        };

        $lower = strtolower($text);
        $intent = match (true) {
            str_contains($lower, 'auth') || str_contains($lower, 'authorization') => 'authorization question',
            str_contains($lower, 'appointment') || str_contains($lower, 'schedule') => 'scheduling request',
            str_contains($lower, 'billing') || str_contains($lower, 'claim') || str_contains($lower, 'invoice') => 'billing inquiry',
            str_contains($lower, 'medication') || str_contains($lower, 'pharmacy') || str_contains($lower, 'rx') => 'medication/pharmacy matter',
            str_contains($lower, 'wellness') || str_contains($lower, 'check-in') => 'wellness check-in',
            str_contains($lower, '?') => 'question pending reply',
            default => null,
        };

        $core = Str::limit($text, 100);

        if ($intent) {
            return Str::limit($prefix.$party.ucfirst($intent).'. '.$core, 160);
        }

        return Str::limit($prefix.$party.$core, 160);
    }

    protected function summarizeWithOpenAi(
        string $body,
        string $subject,
        string $channel,
        string $direction,
        ?string $partyName,
    ): ?string {
        $model = config('communications.ai.openai_model', 'gpt-4o-mini');
        $key = config('communications.ai.openai_api_key');

        $prompt = "Summarize this home-care agency {$direction} {$channel} in one concise sentence (max 120 chars) for staff triage. Party: ".($partyName ?: 'unknown').". Subject: ".($subject ?: 'n/a').". Message: {$body}";

        $response = Http::withToken($key)
            ->acceptJson()
            ->timeout((int) config('communications.ai.timeout', 20))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You write brief clinical operations summaries. No markdown.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 80,
                'temperature' => 0.2,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $content = $response->json('choices.0.message.content');

        return is_string($content) ? Str::limit(trim($content), 160) : null;
    }
}
