<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Models\AiBot;

class AiTestBotService
{
    public function __construct(
        private readonly AiProviderKeyService $providerKeyService,
        private readonly AiTokenUsageService $tokenUsageService,
    ) {}

    /**
     * Test a bot with a user message and return the AI response.
     * Calls the provider directly without WhatsApp sending or RAG.
     *
     * @return array{response: string, tokens: int}
     */
    public function test(AiBot $bot, string $message): array
    {
        $config = $bot->resolveProvider();

        if (empty($config['api_key'])) {
            throw new \RuntimeException('No API key configured for this bot\'s provider.');
        }

        $systemPrompt = $this->buildSystemPrompt($bot);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message],
        ];

        $provider = $this->providerKeyService->resolveProvider($bot->provider);

        $result = $provider->chat($messages, [
            'api_key' => $config['api_key'],
            'model' => $config['chat_model'],
            'temperature' => $bot->temperature ?? 0.3,
        ]);

        $this->tokenUsageService->log([
            'ai_bot_id' => $bot->id,
            'provider' => $config['provider'],
            'model' => $config['chat_model'],
            'request_type' => 'chat',
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'total_tokens' => $result['total_tokens'],
        ]);

        return [
            'response' => $result['text'],
            'tokens' => $result['total_tokens'],
        ];
    }

    /**
     * Build a simple system prompt from the bot's configuration (no RAG).
     */
    private function buildSystemPrompt(AiBot $bot): string
    {
        $parts = [];

        if (! empty($bot->system_prompt)) {
            $parts[] = $bot->system_prompt;
        }

        if (! empty($bot->business_information)) {
            $parts[] = "Business context: {$bot->business_information}";
        }

        if (empty($parts)) {
            $parts[] = 'You are a helpful customer support assistant. Be concise and friendly.';
        }

        $parts[] = 'Format your response concisely.';

        return implode("\n\n", $parts);
    }
}
