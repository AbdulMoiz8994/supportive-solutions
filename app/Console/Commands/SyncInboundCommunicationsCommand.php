<?php

namespace App\Console\Commands;

use App\Services\Communication\CommunicationInboundService;
use App\Services\Directory\IntegrationConnectionHealthRecorder;
use App\Models\IntegrationCredential;
use Illuminate\Console\Command;

class SyncInboundCommunicationsCommand extends Command
{
    protected $signature = 'communications:sync-inbound {--google : Sync Gmail inbox} {--ringcentral : Sync RingCentral call log}';

    protected $description = 'Pull inbound email and call records into the unified communications log';

    public function handle(
        CommunicationInboundService $inbound,
        IntegrationConnectionHealthRecorder $health,
    ): int {
        $syncGoogle = $this->option('google') || (! $this->option('google') && ! $this->option('ringcentral'));
        $syncRingCentral = $this->option('ringcentral') || (! $this->option('google') && ! $this->option('ringcentral'));

        $total = 0;

        if ($syncGoogle) {
            $google = $inbound->syncGoogleInbound((int) config('communications.sync.google_inbound_limit', 25));
            $total += count($google);
            $this->info('Google inbound: '.count($google).' new record(s).');
            if (count($google) > 0) {
                $health->recordSync(IntegrationCredential::KEY_GOOGLE_WORKSPACE);
            }
        }

        if ($syncRingCentral) {
            $calls = $inbound->syncRingCentralCalls((int) config('communications.sync.ringcentral_call_limit', 25));
            $messages = $inbound->syncRingCentralMessages((int) config('communications.sync.ringcentral_message_limit', 25));
            $total += count($calls) + count($messages);
            $this->info('RingCentral calls: '.count($calls).' new record(s).');
            $this->info('RingCentral messages (SMS/fax/voicemail): '.count($messages).' new record(s).');
            if (count($calls) + count($messages) > 0) {
                $health->recordSync(IntegrationCredential::KEY_RINGCENTRAL);
            }
        }

        $this->info("Inbound sync complete — {$total} communication(s) logged.");

        return self::SUCCESS;
    }
}
