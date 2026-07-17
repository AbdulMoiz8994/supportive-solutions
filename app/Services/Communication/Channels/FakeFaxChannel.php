<?php

namespace App\Services\Communication\Channels;

use App\Models\Communication;
use App\Services\Communication\Contracts\CommunicationChannelDriver;
use App\Services\Communication\Contracts\CommunicationChannelResult;
use Illuminate\Support\Str;

class FakeFaxChannel implements CommunicationChannelDriver
{
    public function send(Communication $communication): CommunicationChannelResult
    {
        if (empty($communication->recipient_fax)) {
            return new CommunicationChannelResult(false, failureReason: 'Recipient fax number is required.');
        }

        if (config('communications.channels.fax') === 'fake_fail') {
            return new CommunicationChannelResult(false, failureReason: 'Simulated fax delivery failure.');
        }

        return new CommunicationChannelResult(true, providerMessageId: 'fake-fax-'.Str::uuid());
    }
}
