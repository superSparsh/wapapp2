<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Models\AiBot;
use Illuminate\Support\Facades\Log;

class AiTestBotService
{
    public function __construct(
        private readonly AiProviderKeyService $providerKeyService,
        private readonly AiTokenUsageService $tokenUsageService,
        private readonly KnowledgeBaseProxyService $knowledgeBaseProxy,
        private readonly AiPythonClient $pythonClient,
    ) {}

    /**
     * Test a bot with a user message and return the AI response.
     * Prefers Python RAG (/process_query) when the AI service is available.
     *
     * @param  list<array{role: string, content: string}>  $chatHistory
     * @return array{response: string, tokens: int}
     */
    public function test(AiBot $bot, string $message, array $chatHistory = []): array
    {
        if ($this->pythonClient->isConfigured()) {
            try {
                $payload = $this->knowledgeBaseProxy->processQuery(
                    queryText: $message,
                    botId: $bot->uuid,
                    chatHistory: $chatHistory,
                );

                $text = is_string($payload['response'] ?? null)
                    ? $payload['response']
                    : (string) json_encode($payload['response'] ?? $payload);

                if (filled($payload['_error'] ?? null) || AiChatService::isTransferFallbackMessage($text)) {
                    Log::warning('AI process_query unusable in test-bot; falling back to direct chat', [
                        'bot_id' => $bot->id,
                        'error' => (string) ($payload['_error'] ?? 'transfer_fallback'),
                    ]);
                } else {
                    $tokens = (int) ($payload['total_tokens'] ?? $payload['tokens'] ?? 0);
                    $prompt = (int) ($payload['prompt_tokens'] ?? 0);
                    $completion = (int) ($payload['completion_tokens'] ?? 0);
                    if ($tokens <= 0) {
                        $tokens = $prompt + $completion;
                    }

                    if ($tokens > 0) {
                        $config = $bot->resolveProvider();
                        $this->tokenUsageService->log([
                            'ai_bot_id' => $bot->id,
                            'provider' => $payload['provider'] ?? $config['provider'] ?? $bot->provider,
                            'model' => (string) ($payload['model'] ?? $config['chat_model'] ?? 'unknown'),
                            'request_type' => 'chat',
                            'prompt_tokens' => $prompt,
                            'completion_tokens' => $completion,
                            'total_tokens' => $tokens,
                        ]);
                    }

                    return [
                        'response' => $text,
                        'tokens' => $tokens,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('AI process_query failed; falling back to direct chat', [
                    'bot_id' => $bot->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $config = $bot->resolveProvider();

        if (empty($config['api_key'])) {
            throw new \RuntimeException('No API key configured for this bot\'s provider.');
        }

        $systemPrompt = $this->buildSystemPrompt($bot);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($chatHistory as $turn) {
            $role = (string) ($turn['role'] ?? '');
            $content = (string) ($turn['content'] ?? '');
            if ($content === '' || ! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

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
