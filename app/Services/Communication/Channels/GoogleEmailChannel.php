<?php

namespace App\Services\Communication\Channels;

use App\Models\Communication;
use App\Services\Communication\Contracts\CommunicationChannelDriver;
use App\Services\Communication\Contracts\CommunicationChannelResult;
use App\Services\Integrations\GoogleWorkspaceClient;

class GoogleEmailChannel implements CommunicationChannelDriver
{
  public function __construct(
    protected GoogleWorkspaceClient $client,
  ) {}

  public function send(Communication $communication): CommunicationChannelResult
  {
    if (empty($communication->recipient_email)) {
      return new CommunicationChannelResult(false, failureReason: 'Recipient email is required.');
    }

    $result = $this->client->sendEmail(
      (string) $communication->recipient_email,
      (string) ($communication->subject ?? 'Message from BeydounTech'),
      (string) ($communication->body ?? '')
    );

    return new CommunicationChannelResult(
      $result['success'],
      $result['provider_message_id'],
      $result['failure_reason']
    );
  }
}
