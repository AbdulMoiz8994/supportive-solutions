<?php

namespace App\Services\Communication\Channels;

use App\Models\Communication;
use App\Services\Communication\Contracts\CommunicationChannelDriver;
use App\Services\Communication\Contracts\CommunicationChannelResult;
use App\Services\Integrations\RingCentralClient;

class RingCentralSmsChannel implements CommunicationChannelDriver
{
  public function __construct(
    protected RingCentralClient $client,
  ) {}

  public function send(Communication $communication): CommunicationChannelResult
  {
    if (empty($communication->recipient_phone)) {
      return new CommunicationChannelResult(false, failureReason: 'Recipient phone number is required.');
    }

    $result = $this->client->sendSms(
      (string) $communication->recipient_phone,
      (string) ($communication->body ?? $communication->subject ?? '')
    );

    return new CommunicationChannelResult(
      $result['success'],
      $result['provider_message_id'],
      $result['failure_reason']
    );
  }
}
