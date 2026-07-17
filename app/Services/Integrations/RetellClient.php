<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RetellClient
{
    protected ?string $lastError = null;

    public function isConfigured(): bool
    {
        return filled(config('retell.api_key')) && filled(config('retell.agent_id'));
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! filled(config('retell.api_key'))) {
            return ['success' => false, 'message' => 'Retell API key is not configured.'];
        }

        $response = $this->request()->get('/list-agents');

        if (! $response->successful()) {
            return [
                'success' => false,
                'message' => 'Retell API error: '.$this->parseError($response),
            ];
        }

        $agents = $response->json();
        $count = is_array($agents) ? count($agents) : 0;

        return [
            'success' => true,
            'message' => "Retell connected — {$count} agent(s) available.",
        ];
    }

    /**
     * @return array{success: bool, call_id: ?string, failure_reason: ?string}
     */
    public function createOutboundCall(string $toNumber, array $dynamicVariables = [], ?string $fromNumber = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'call_id' => null, 'failure_reason' => 'Retell is not configured.'];
        }

        $response = $this->request()->post('/v2/create-phone-call', [
            'from_number' => $fromNumber ?: config('retell.from_number'),
            'to_number' => $this->normalizePhone($toNumber),
            'agent_id' => config('retell.agent_id'),
            'retell_llm_dynamic_variables' => $dynamicVariables,
        ]);

        if (! $response->successful()) {
            return [
                'success' => false,
                'call_id' => null,
                'failure_reason' => $this->parseError($response),
            ];
        }

        return [
            'success' => true,
            'call_id' => (string) ($response->json('call_id') ?? $response->json('id') ?? Str::uuid()),
            'failure_reason' => null,
        ];
    }

    protected function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken((string) config('retell.api_key'))
            ->acceptJson()
            ->timeout((int) config('retell.timeout', 30))
            ->baseUrl('https://api.retellai.com');
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) === 10) {
            return '+1'.$digits;
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        return '+'.$digits;
    }

    protected function parseError(\Illuminate\Http\Client\Response $response): string
    {
        $message = $response->json('message') ?? $response->json('error') ?? $response->body();

        return Str::limit(is_string($message) ? $message : json_encode($message), 500);
    }
}
