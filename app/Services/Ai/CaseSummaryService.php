<?php

namespace App\Services\Ai;

use App\Models\Client;
use App\Models\Employee;

/**
 * AI Assistant Panel (EMR: "summarize client cases, draft messages, provide
 * on-screen guidance"). Feeds compact, already-loaded facts to Claude and gets
 * back a short staff-facing summary + the single most important next action.
 */
class CaseSummaryService
{
    public function __construct(protected ClaudeService $claude) {}

    public function isAvailable(): bool
    {
        return $this->claude->isConfigured();
    }

    /**
     * @return array{summary: string, next_action: string, flags: array<int, string>}
     */
    public function summarizeClient(Client $client): array
    {
        return $this->summarize('client', $this->clientFacts($client));
    }

    /**
     * @return array{summary: string, next_action: string, flags: array<int, string>}
     */
    public function summarizeCaregiver(Employee $caregiver): array
    {
        return $this->summarize('caregiver', $this->caregiverFacts($caregiver));
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array{summary: string, next_action: string, flags: array<int, string>}
     */
    protected function summarize(string $kind, array $facts): array
    {
        $system = "You are an assistant for home-care office staff. Summarize this {$kind}'s case for a "
            ."busy coordinator. Be factual and concise — no markdown, no headers. Return ONLY a JSON object: "
            .'{"summary": "3-4 short sentences", "next_action": "the single most important next step", '
            .'"flags": ["short risk/attention flags"]}. Base everything strictly on the facts provided; do not invent.';

        $messages = [$this->claude->userMessage(
            ucfirst($kind)." facts:\n".json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n\nReturn JSON only."
        )];

        $json = $this->claude->json($messages, ['system' => $system, 'max_tokens' => 500]);

        return [
            'summary' => is_string($json['summary'] ?? null) ? trim($json['summary']) : '',
            'next_action' => is_string($json['next_action'] ?? null) ? trim($json['next_action']) : '',
            'flags' => array_values(array_filter(
                is_array($json['flags'] ?? null) ? $json['flags'] : [],
                'is_string'
            )),
        ];
    }

    /** @return array<string, mixed> */
    protected function clientFacts(Client $client): array
    {
        $auth = $client->currentAuthorization();

        return array_filter([
            'name' => trim($client->first_name.' '.$client->last_name),
            'age' => $client->age,
            'status' => $client->current_status_name ?? $client->status,
            'days_in_current_status' => $client->days_in_current_status,
            'status_needs_attention' => $client->status_needs_attention,
            'county' => $client->county,
            'coverage_type' => $client->coverageType?->name,
            'authorization' => $auth ? array_filter([
                'units' => $auth->total_units,
                'hours_per_week' => $auth->hours_per_week_value,
                'effective_status' => $auth->effectiveStatusForProgram($client->program_label),
                'days_until_expiry' => $auth->days_until_expiry,
                'needs_renewal' => $auth->needs_renewal,
            ], fn ($v) => $v !== null) : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** @return array<string, mixed> */
    protected function caregiverFacts(Employee $caregiver): array
    {
        return array_filter([
            'name' => $caregiver->name,
            'status' => $caregiver->status,
            'is_champs_associated' => $caregiver->is_champs_associated,
            'id_expiry_status' => $caregiver->id_expiry_status,
            'credential_alerts' => collect($caregiver->credential_alerts)->pluck('label')->all(),
            'assigned_clients' => $caregiver->assigned_client_count,
            'weekly_hours' => $caregiver->total_weekly_hours,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }
}
