<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Contracts\OutboundMessageGateway;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Enums\MessageStatus;
use App\Models\Message;

class DelegatingOutboundMessageGateway implements OutboundMessageGateway
{
    public function __construct(
        private readonly AlibabaCamsClient $client,
        private readonly AlibabaOutboundMessageGateway $alibabaGateway,
        private readonly LocalOutboundMessageGateway $localGateway,
    ) {}

    public function send(Message $message): void
    {
        $driver = (string) config('whatsapp.outbound_driver', 'alibaba');

        if ($driver === 'local') {
            $this->localGateway->send($message);

            return;
        }

        if ($this->client->isConfigured()) {
            $this->alibabaGateway->send($message);

            return;
        }

        if ($message->status === MessageStatus::Sent) {
            return;
        }

        $message->forceFill([
            'status' => MessageStatus::Failed,
            'failed_at' => now(),
            'failed_reason' => 'WhatsApp provider (Alibaba CAMS) is not configured. Set ALIBABA_ACCESS_KEY_ID / ALIBABA_ACCESS_KEY_SECRET.',
        ])->save();
    }
}
