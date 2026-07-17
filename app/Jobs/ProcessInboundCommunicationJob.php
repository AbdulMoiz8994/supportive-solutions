<?php

namespace App\Jobs;

use App\Services\Communication\CommunicationInboundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Processes a raw inbound webhook payload from RingCentral or Retell
 * asynchronously so the webhook endpoint returns 204 immediately and the
 * OpenAI triage call never causes a provider-side timeout or duplicate retry.
 *
 * Queue: 'communications' (falls back to default if not configured).
 * Retries: 3 attempts with exponential back-off (30s, 120s).
 */
class ProcessInboundCommunicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [30, 120];

    public int $timeout = 90;

    public function __construct(
        private readonly array $payload,
        private readonly string $provider, // 'ringcentral' | 'retell'
    ) {
        $this->onQueue(config('communications.inbound.queue', 'default'));
    }

    public function handle(CommunicationInboundService $inbound): void
    {
        match ($this->provider) {
            'ringcentral' => $inbound->recordFromRingCentralWebhook($this->payload),
            'retell'      => $inbound->recordFromRetellWebhook($this->payload),
            default       => null,
        };
    }
}
