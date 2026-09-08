<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Message;

interface OutboundMessageGateway
{
    public function send(Message $message): void;
}
