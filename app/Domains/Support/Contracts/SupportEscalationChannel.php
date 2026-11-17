<?php

declare(strict_types=1);

namespace App\Domains\Support\Contracts;

use App\Models\Conversation;

/**
 * Destination for support bot escalations.
 * Inbox now; Tittu Support ticket hub later.
 */
interface SupportEscalationChannel
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{channel: string, conversation_id: int, already_escalated: bool, message_sent: bool, end_flow: bool}
     */
    public function escalate(Conversation $conversation, array $context = []): array;
}
