<?php

namespace App\Services\Communication;

use App\Models\CallLog;
use App\Models\Client;
use App\Models\Employee;
use App\Models\User;
use App\Services\Integrations\RingCentralClient;

/**
 * Powers the "Call Now" button on the caregiver app.
 *
 * When RingCentral is configured and both phone legs are known, the call is
 * bridged server-side (RingOut): RingCentral rings the caregiver first, then
 * dials the client — the client sees the agency caller ID, not the caregiver's
 * personal number. Otherwise it degrades gracefully to a `manual` record and
 * the app dials the returned `tel:` number natively. Either way the attempt is
 * logged for audit.
 */
class ClickToCallService
{
    public function __construct(
        protected RingCentralClient $ringCentral
    ) {}

    public function callClient(User $user, Employee $caregiver, Client $client): CallLog
    {
        $to = trim((string) $client->phone);
        $from = trim((string) $caregiver->phone);
        $agencyCallerId = trim((string) config('ringcentral.from_number')) ?: null;

        $attributes = [
            'organization_id'  => $caregiver->organization_id,
            'user_id'          => $user->id,
            'employee_id'      => $caregiver->id,
            'client_id'        => $client->id,
            'client_name'      => trim("{$client->first_name} {$client->last_name}"),
            'direction'        => 'outbound',
            'to_number'        => $to !== '' ? $to : null,
            'from_number'      => $from !== '' ? $from : null,
            'provider'         => null,
            'provider_call_id' => null,
            'failure_reason'   => null,
            'mode'             => CallLog::MODE_MANUAL,
            'status'           => CallLog::STATUS_MANUAL,
        ];

        // Server-bridged RingOut when we have both legs and RingCentral is set up.
        if ($to !== '' && $from !== '' && $this->ringCentral->isConfigured()) {
            $result = $this->ringCentral->ringOut($to, $from, $agencyCallerId);
            $attributes['provider'] = 'ringcentral';

            if ($result['success']) {
                $attributes['mode'] = CallLog::MODE_RINGOUT;
                $attributes['status'] = CallLog::STATUS_INITIATED;
                $attributes['provider_call_id'] = $result['call_id'] ?: null;
            } else {
                // Keep the button working: fall back to native dialling, but
                // record why the bridge failed so admins can fix the config.
                $attributes['failure_reason'] = $result['failure_reason'];
            }
        }

        return CallLog::create($attributes);
    }
}
