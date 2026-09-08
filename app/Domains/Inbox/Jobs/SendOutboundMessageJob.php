<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Jobs;

use App\Domains\Inbox\Contracts\OutboundMessageGateway;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOutboundMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $messageId,
    ) {}

    public function handle(OutboundMessageGateway $gateway): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message === null) {
            return;
        }

        $gateway->send($message);
    }
}
