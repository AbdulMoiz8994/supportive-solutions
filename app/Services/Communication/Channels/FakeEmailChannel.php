<?php

namespace App\Services\Communication\Channels;

use App\Models\Communication;
use App\Services\Communication\Contracts\CommunicationChannelDriver;
use App\Services\Communication\Contracts\CommunicationChannelResult;
use Illuminate\Support\Str;

class FakeEmailChannel implements CommunicationChannelDriver
{
    public function send(Communication $communication): CommunicationChannelResult
    {
        if (empty($communication->recipient_email)) {
            return new CommunicationChannelResult(false, failureReason: 'Recipient email is required.');
        }

        if (config('communications.channels.email') === 'fake_fail') {
            return new CommunicationChannelResult(false, failureReason: 'Simulated email delivery failure.');
        }

        return new CommunicationChannelResult(true, providerMessageId: 'fake-email-'.Str::uuid());
    }
}
