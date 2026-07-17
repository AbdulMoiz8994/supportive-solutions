<?php

namespace App\Services\Communication;

use App\Models\Communication;
use App\Services\Communication\Channels\FakeEmailChannel;
use App\Services\Communication\Channels\FakeFaxChannel;
use App\Services\Communication\Channels\FakeSmsChannel;
use App\Services\Communication\Channels\GoogleEmailChannel;
use App\Services\Communication\Channels\RingCentralFaxChannel;
use App\Services\Communication\Channels\RingCentralSmsChannel;
use App\Services\Communication\Contracts\CommunicationChannelDriver;
use InvalidArgumentException;

class CommunicationChannelManager
{
  public function driver(string $channel): CommunicationChannelDriver
  {
    $driverName = config("communications.channels.{$channel}", 'fake');

    return match ($channel) {
      Communication::CHANNEL_EMAIL => $this->resolveEmailDriver($driverName),
      Communication::CHANNEL_FAX => $this->resolveFaxDriver($driverName),
      Communication::CHANNEL_SMS => $this->resolveSmsDriver($driverName),
      Communication::CHANNEL_INTERNAL_MESSAGE => new FakeEmailChannel,
      default => throw new InvalidArgumentException("Unsupported communication channel [{$channel}]."),
    };
  }

  protected function resolveEmailDriver(string $driverName): CommunicationChannelDriver
  {
    return match ($driverName) {
      'google', 'google_workspace' => app(GoogleEmailChannel::class),
      'fake_fail' => new FakeEmailChannel,
      'fake' => new FakeEmailChannel,
      default => new FakeEmailChannel,
    };
  }

  protected function resolveFaxDriver(string $driverName): CommunicationChannelDriver
  {
    return match ($driverName) {
      'ringcentral' => app(RingCentralFaxChannel::class),
      'fake_fail' => new FakeFaxChannel,
      'fake' => new FakeFaxChannel,
      default => new FakeFaxChannel,
    };
  }

  protected function resolveSmsDriver(string $driverName): CommunicationChannelDriver
  {
    return match ($driverName) {
      'ringcentral' => app(RingCentralSmsChannel::class),
      'fake_fail' => new FakeSmsChannel,
      'fake' => new FakeSmsChannel,
      default => new FakeSmsChannel,
    };
  }
}
