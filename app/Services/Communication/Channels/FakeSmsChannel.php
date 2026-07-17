<?php

namespace App\Services\Communication\Channels;

use App\Models\Communication;
use App\Services\Communication\Contracts\CommunicationChannelDriver;
use App\Services\Communication\Contracts\CommunicationChannelResult;
use Illuminate\Support\Str;

class FakeSmsChannel implements CommunicationChannelDriver
{
    public function send(Communication $communication): CommunicationChannelResult
    {
        if (empty($communication->recipient_phone)) {
            return new CommunicationChannelResult(false, failureReason: 'Recipient phone number is required.');
        }

        if (config('communications.channels.sms') === 'fake_fail') {
            return new CommunicationChannelResult(false, failureReason: 'Simulated SMS delivery failure.');
        }

        return new CommunicationChannelResult(true, providerMessageId: 'fake-sms-'.Str::uuid());
    }
}
