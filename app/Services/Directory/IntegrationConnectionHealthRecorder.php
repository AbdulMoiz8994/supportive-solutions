<?php

namespace App\Services\Directory;

use App\Models\Contact;
use App\Models\IntegrationConnectionHealth;
use Illuminate\Support\Carbon;

class IntegrationConnectionHealthRecorder
{
    /**
     * Record a successful data sync for all directory cards linked to a credential key.
     */
    public function recordSync(string $credentialKey, ?Carbon $at = null): void
    {
        $this->touchContacts($credentialKey, [
            'last_sync_at' => $at ?? now(),
            'status' => IntegrationConnectionHealth::STATUS_CONNECTED,
        ]);
    }

    /**
     * Record a batch job completion (payroll build, claim submit, etc.).
     */
    public function recordBatch(string $credentialKey, ?Carbon $at = null): void
    {
        $this->touchContacts($credentialKey, [
            'last_batch_at' => $at ?? now(),
            'last_sync_at' => $at ?? now(),
            'status' => IntegrationConnectionHealth::STATUS_CONNECTED,
        ]);
    }

    /**
     * @param  array{last_sync_at?: Carbon, last_batch_at?: Carbon, status?: string, message?: ?string}  $data
     */
    protected function touchContacts(string $credentialKey, array $data): void
    {
        Contact::withoutGlobalScopes()
            ->where('integration_credential_key', $credentialKey)
            ->each(function (Contact $contact) use ($data) {
                $health = IntegrationConnectionHealth::query()->firstOrNew(['contact_id' => $contact->id]);
                $health->fill(array_filter([
                    'status' => $data['status'] ?? $health->status ?? IntegrationConnectionHealth::STATUS_CONNECTED,
                    'message' => $data['message'] ?? $health->message,
                    'last_sync_at' => $data['last_sync_at'] ?? $health->last_sync_at,
                    'last_batch_at' => $data['last_batch_at'] ?? $health->last_batch_at,
                    'last_tested_at' => $health->last_tested_at ?? now(),
                    'errors_30d' => $health->errors_30d ?? 0,
                ]));
                $health->save();
            });
    }
}
