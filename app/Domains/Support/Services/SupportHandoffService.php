<?php

declare(strict_types=1);

namespace App\Domains\Support\Services;

use App\Domains\Support\Contracts\SupportEscalationChannel;
use App\Models\Conversation;

/**
 * Company support-bot escalation entry point.
 * Default channel is Inbox; swap binding later for Tittu Support.
 */
class SupportHandoffService
{
    public function __construct(
        private readonly SupportEscalationChannel $channel,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array{channel: string, conversation_id: int, already_escalated: bool, message_sent: bool, end_flow: bool}
     */
    public function handoffToAgent(Conversation $conversation, array $context = []): array
    {
        return $this->channel->escalate($conversation, $context);
    }
}
