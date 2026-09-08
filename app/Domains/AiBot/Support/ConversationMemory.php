<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Support;

use App\Models\Conversation;
use App\Models\Message;

class ConversationMemory
{
    private const MAX_MESSAGES = 5;

    /**
     * Build a sliding window of recent messages from the conversation.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public static function build(Conversation $conversation, int $maxMessages = self::MAX_MESSAGES): array
    {
        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->limit($maxMessages)
            ->get()
            ->reverse()
            ->values();

        $memory = [];

        foreach ($messages as $message) {
            $role = $message->direction->value === 'inbound' ? 'user' : 'assistant';
            $content = trim((string) $message->body);

            if ($content === '') {
                continue;
            }

            $memory[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return $memory;
    }
}
