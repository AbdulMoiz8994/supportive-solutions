<?php

namespace App\Services\Communication\Channels;

use App\Models\Communication;
use App\Services\Communication\CommunicationAttachmentService;
use App\Services\Communication\Contracts\CommunicationChannelDriver;
use App\Services\Communication\Contracts\CommunicationChannelResult;
use App\Services\Integrations\RingCentralClient;
use Illuminate\Support\Facades\Storage;

class RingCentralFaxChannel implements CommunicationChannelDriver
{
  public function __construct(
    protected RingCentralClient $client,
  ) {}

  public function send(Communication $communication): CommunicationChannelResult
  {
    if (empty($communication->recipient_fax)) {
      return new CommunicationChannelResult(false, failureReason: 'Recipient fax number is required.');
    }

    $communication->loadMissing('attachments');
    $attachment = $communication->attachments->first();

    if (! $attachment) {
      return new CommunicationChannelResult(false, failureReason: 'Fax attachment is required.');
    }

    $path = $attachment->stored_path;
    $disk = Storage::disk($attachment->disk);

    if (! $disk->exists($path)) {
      return new CommunicationChannelResult(false, failureReason: 'Fax attachment is not readable.');
    }

    $contents = $disk->get($path);

    if ($contents === null || $contents === '') {
      return new CommunicationChannelResult(false, failureReason: 'Fax attachment is empty.');
    }

    $result = $this->client->sendFax(
      (string) $communication->recipient_fax,
      $contents,
      $attachment->original_name,
      (string) ($communication->body ?? $communication->subject),
      isContents: true,
    );

    return new CommunicationChannelResult(
      $result['success'],
      $result['provider_message_id'],
      $result['failure_reason']
    );
  }
}
