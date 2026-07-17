<?php

namespace App\Services\Communication\Contracts;

class CommunicationChannelResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $failureReason = null,
    ) {}
}
