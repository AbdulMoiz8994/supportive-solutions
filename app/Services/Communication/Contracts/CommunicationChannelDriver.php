<?php

namespace App\Services\Communication\Contracts;

use App\Models\Communication;

interface CommunicationChannelDriver
{
    public function send(Communication $communication): CommunicationChannelResult;
}
