<?php

declare(strict_types=1);

namespace App\Domains\Support\Chatbot;

use App\Domains\Support\Services\SupportHandoffService;
use App\Models\Conversation;

/**
 * Chatbot function_registry handler: handoff_to_inbox
 */
class HandoffToInboxHandler
{
    public function __construct(
        private readonly SupportHandoffService $handoffService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $variables
     * @return array{channel: string, conversation_id: int, already_escalated: bool, message_sent: bool, end_flow: bool}
     */
    public function handle(array $params, array $variables, Conversation $conversation): array
    {
        $confirmation = trim((string) ($params['confirmation_message'] ?? $params['message'] ?? ''));
        $sendConfirmation = ! array_key_exists('send_confirmation', $params)
            || filter_var($params['send_confirmation'], FILTER_VALIDATE_BOOLEAN);

        $context = [
            'send_confirmation' => $sendConfirmation,
            'source' => 'chatbot_function',
            'variables' => $variables,
        ];

        if ($confirmation !== '') {
            $context['confirmation_message'] = $confirmation;
        }

        return $this->handoffService->handoffToAgent($conversation, $context);
    }
}
