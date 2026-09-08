<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Services\Contracts\OutboundMessageGateway;

class LocalOutboundMessageGateway implements OutboundMessageGateway
{
    public function send(Message $message): void
    {
        if ($message->status === MessageStatus::Sent) {
            return;
        }

        $message->forceFill([
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
            'external_message_id' => $message->external_message_id ?? ('local_'.$message->uuid),
        ])->save();
    }
}
