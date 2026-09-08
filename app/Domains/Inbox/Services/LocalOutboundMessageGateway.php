<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Contracts\OutboundMessageGateway;
use App\Enums\MessageStatus;
use App\Models\Message;

/**
 * Stub gateway until Alibaba CAMS / Meta send is wired.
 * Marks messages as sent locally so inbox UX and tests work end-to-end.
 */
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
            'external_message_id' => $message->external_message_id ?? 'local_'.$message->uuid,
        ])->save();
    }
}
