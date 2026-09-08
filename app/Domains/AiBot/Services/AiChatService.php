<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Domains\AiBot\Support\AiResponseFormatter;
use App\Domains\AiBot\Support\ConversationMemory;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Models\AiBot;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    public function __construct(
        private readonly AiProviderKeyService $providerKeyService,
        private readonly AiRagService $ragService,
        private readonly AiTokenUsageService $tokenUsageService,
        private readonly InboxOutboundService $outboundService,
    ) {}

    /**
     * Process a user message through the AI bot and send the response.
     */
    public function processMessage(Conversation $conversation, AiBot $bot, string $userMessage): ?string
    {
        try {
            $config = $bot->resolveProvider();

            if (empty($config['api_key'])) {
                Log::warning('AI bot has no API key', ['bot_id' => $bot->id]);
                return null;
            }

            // Build conversation memory (last 5 messages)
            $memory = ConversationMemory::build($conversation);

            // Add current user message
            $memory[] = ['role' => 'user', 'content' => $userMessage];

            // Build system prompt
            $systemPrompt = $this->buildSystemPrompt($bot, $userMessage);

            // Prepend system message
            $messages = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $memory,
            );

            // Call the LLM provider
            $provider = $this->providerKeyService->resolveProvider($bot->provider);

            $result = $provider->chat($messages, [
                'api_key' => $config['api_key'],
                'model' => $config['chat_model'],
                'temperature' => $bot->temperature ?? 0.3,
            ]);

            // Log token usage
            $this->tokenUsageService->log([
                'ai_bot_id' => $bot->id,
                'provider' => $config['provider'],
                'model' => $config['chat_model'],
                'request_type' => 'chat',
                'prompt_tokens' => $result['prompt_tokens'],
                'completion_tokens' => $result['completion_tokens'],
                'total_tokens' => $result['total_tokens'],
                'conversation_id' => $conversation->id,
            ]);

            // Format response for WhatsApp
            $responseText = AiResponseFormatter::forWhatsApp($result['text']);

            if (blank($responseText)) {
                return null;
            }

            // Send the response
            $this->outboundService->sendText($conversation, $responseText, enforceWindow: false);

            return $responseText;
        } catch (\Throwable $e) {
            Log::error('AI chat processing failed', [
                'bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build the system prompt with optional RAG context.
     */
    private function buildSystemPrompt(AiBot $bot, string $userMessage): string
    {
        $parts = [];

        // Bot's system prompt
        if (! empty($bot->system_prompt)) {
            $parts[] = $bot->system_prompt;
        }

        // Bot's business information (inline)
        if (! empty($bot->business_information)) {
            $parts[] = "Business context: {$bot->business_information}";
        }

        // RAG context from knowledge base
        $ragContext = $this->ragService->retrieveContext($bot, $userMessage);
        if (! empty($ragContext)) {
            $parts[] = $ragContext;
        }

        // Default instructions if no system prompt
        if (empty($parts)) {
            $parts[] = "You are a helpful customer support assistant for WhatsApp. Be concise and friendly. Keep responses under 300 words.";
        }

        // WhatsApp-specific instruction
        $parts[] = "Important: Format your response for WhatsApp. Use *bold* for emphasis. Keep it concise and conversational.";

        return implode("\n\n", $parts);
    }
}
