<?php

namespace App\Services\Communication;

use App\Models\Client;
use App\Models\Communication;
use App\Services\Integrations\RetellClient;
use Carbon\Carbon;

/**
 * Monthly wellness-call pipeline (client review D4): places an AI voice call
 * (Retell) to every active client once per month. The call verifies services
 * were delivered; the Retell webhook records the transcript and flips the
 * month's compliance form to "Verified" when no concern is raised.
 */
class WellnessCallService
{
    public function __construct(protected RetellClient $retell) {}

    /**
     * @return array{placed:int, already_called:int, no_phone:int, failed:int}
     */
    public function placeMonthlyCalls(?int $organizationId = null, ?Carbon $period = null): array
    {
        $period = $period ?? now();
        $counts = ['placed' => 0, 'already_called' => 0, 'no_phone' => 0, 'failed' => 0];

        if (! $this->retell->isConfigured()) {
            return $counts;
        }

        $clients = Client::withoutGlobalScopes()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('status', 'Active')
            ->get();

        $calledClientIds = $this->clientsAlreadyCalled($period);

        foreach ($clients as $client) {
            if ($calledClientIds->contains($client->id)) {
                $counts['already_called']++;

                continue;
            }

            if (blank($client->phone)) {
                $counts['no_phone']++;

                continue;
            }

            $result = $this->retell->createOutboundCall((string) $client->phone, [
                'client_id' => (string) $client->id,
                'client_name' => trim($client->first_name.' '.$client->last_name),
                'wellness_call' => true,
                'campaign' => 'wellness',
                'period' => $period->format('Y-m'),
            ]);

            if (! $result['success']) {
                $counts['failed']++;

                continue;
            }

            $this->recordPlacedCall($client, $result['call_id'], $period);
            $counts['placed']++;
        }

        return $counts;
    }

    /**
     * Place (or re-place) a wellness call for one client — used by the manual
     * trigger on the client compliance tab.
     *
     * @return array{success: bool, message: string, call_id: ?string}
     */
    public function placeCallForClient(Client $client, bool $force = false): array
    {
        if (! $this->retell->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Retell is not configured — add credentials in Global Settings.',
                'call_id' => null,
            ];
        }

        if (blank($client->phone)) {
            return [
                'success' => false,
                'message' => 'Client has no phone number on file.',
                'call_id' => null,
            ];
        }

        $period = now();

        if (! $force && $this->clientsAlreadyCalled($period)->contains($client->id)) {
            return [
                'success' => true,
                'message' => 'Wellness call already placed for '.$period->format('F Y').'.',
                'call_id' => null,
            ];
        }

        $result = $this->retell->createOutboundCall((string) $client->phone, [
            'client_id' => (string) $client->id,
            'client_name' => trim($client->first_name.' '.$client->last_name),
            'wellness_call' => true,
            'campaign' => 'wellness',
            'period' => $period->format('Y-m'),
        ]);

        if (! $result['success']) {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Retell could not place the call.',
                'call_id' => null,
            ];
        }

        $this->recordPlacedCall($client, $result['call_id'], $period);

        return [
            'success' => true,
            'message' => 'Wellness call queued for '.trim($client->first_name.' '.$client->last_name).'.',
            'call_id' => $result['call_id'],
        ];
    }

    /**
     * Client IDs with a wellness call already logged in the period —
     * makes reruns of the scheduled command idempotent.
     */
    protected function clientsAlreadyCalled(Carbon $period): \Illuminate\Support\Collection
    {
        return Communication::withoutGlobalScopes()
            ->where('related_type', Client::class)
            ->where('channel', Communication::CHANNEL_CALL)
            ->whereJsonContains('metadata->wellness_call', true)
            ->whereBetween('created_at', [
                $period->copy()->startOfMonth(),
                $period->copy()->endOfMonth()->endOfDay(),
            ])
            ->pluck('related_id')
            ->filter()
            ->unique();
    }

    protected function recordPlacedCall(Client $client, ?string $callId, Carbon $period): void
    {
        Communication::create([
            'organization_id' => $client->organization_id,
            'related_type' => Client::class,
            'related_id' => $client->id,
            'channel' => Communication::CHANNEL_CALL,
            'direction' => Communication::DIRECTION_OUTBOUND,
            'subject' => 'Monthly wellness call',
            'body' => 'Automated wellness call placed for '.$period->format('F Y').'.',
            'status' => Communication::STATUS_QUEUED,
            'provider_message_id' => $callId,
            'sent_at' => now(),
            'metadata' => [
                'handled_by' => 'ai_va',
                'party_name' => trim($client->first_name.' '.$client->last_name),
                'party_type' => 'client',
                'wellness_call' => true,
                'provider' => 'Retell',
                'delivery_status' => 'queued',
                'source' => 'wellness_scheduler',
                'period' => $period->format('Y-m'),
            ],
        ]);
    }
}
