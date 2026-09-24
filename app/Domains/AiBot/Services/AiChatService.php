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
    /** Python AI service returns this on RAG crash (HTTP 200 + _error). Never send as a real reply. */
    public const TRANSFER_FALLBACK_MESSAGE = "I'm transferring your chat to our available executive.";

    public function __construct(
        private readonly AiProviderKeyService $providerKeyService,
        private readonly AiRagService $ragService,
        private readonly AiTokenUsageService $tokenUsageService,
        private readonly InboxOutboundService $outboundService,
        private readonly KnowledgeBaseProxyService $knowledgeBaseProxy,
        private readonly AiPythonClient $pythonClient,
    ) {}

    /**
     * Process a user message through the AI bot and send the response.
     */
    public function processMessage(Conversation $conversation, AiBot $bot, string $userMessage): ?string
    {
        try {
            // Prefer Python RAG + chat (Chroma) when AI service is up — legacy parity.
            if ($this->pythonClient->isConfigured()) {
                try {
                    $history = ConversationMemory::build($conversation);
                    $payload = $this->knowledgeBaseProxy->processQuery(
                        queryText: $userMessage,
                        botId: $bot->uuid,
                        chatHistory: $history,
                    );

                    if ($this->pythonPayloadLooksLikeFailure($payload)) {
                        Log::warning('AI process_query unusable; falling back to local chat', [
                            'bot_id' => $bot->id,
                            'error' => (string) ($payload['_error'] ?? 'transfer_fallback_or_empty'),
                        ]);
                    } else {
                        $responseText = AiResponseFormatter::forWhatsApp(
                            is_string($payload['response'] ?? null)
                                ? $payload['response']
                                : (string) ($payload['response'] ?? '')
                        );

                        if (filled($responseText)) {
                            $this->logPythonUsage($bot, $payload, $conversation->id);

                            $this->outboundService->sendText($conversation, $responseText, enforceWindow: false);

                            return $responseText;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('AI process_query failed; falling back to local chat', [
                        'bot_id' => $bot->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $this->replyViaLocalProvider($conversation, $bot, $userMessage);
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
     * Direct OpenAI/Gemini (etc.) chat when RAG is down — still a real AI reply.
     */
    private function replyViaLocalProvider(Conversation $conversation, AiBot $bot, string $userMessage): ?string
    {
        $config = $bot->resolveProvider();

        if (empty($config['api_key'])) {
            Log::warning('AI bot has no API key', ['bot_id' => $bot->id]);

            return null;
        }

        $memory = ConversationMemory::build($conversation);
        $memory[] = ['role' => 'user', 'content' => $userMessage];

        $systemPrompt = $this->buildSystemPrompt($bot, $userMessage);
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $memory,
        );

        $provider = $this->providerKeyService->resolveProvider($bot->provider);

        $result = $provider->chat($messages, [
            'api_key' => $config['api_key'],
            'model' => $config['chat_model'],
            'temperature' => $bot->temperature ?? 0.3,
        ]);

        $this->tokenUsageService->log([
            'ai_bot_id' => $bot->id,
            'provider' => is_string($config['provider'])
                ? $config['provider']
                : (string) ($config['provider']->value ?? $config['provider']),
            'model' => $config['chat_model'],
            'request_type' => 'chat',
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'total_tokens' => $result['total_tokens'],
            'conversation_id' => $conversation->id,
        ]);

        $responseText = AiResponseFormatter::forWhatsApp($result['text']);

        if (blank($responseText) || $this->isTransferFallbackMessage($responseText)) {
            return null;
        }

        $this->outboundService->sendText($conversation, $responseText, enforceWindow: false);

        return $responseText;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function pythonPayloadLooksLikeFailure(array $payload): bool
    {
        if (filled($payload['_error'] ?? null)) {
            return true;
        }

        $response = is_string($payload['response'] ?? null)
            ? trim($payload['response'])
            : trim((string) ($payload['response'] ?? ''));

        return $response === '' || $this->isTransferFallbackMessage($response);
    }

    public static function isTransferFallbackMessage(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));
        $needle = mb_strtolower(self::TRANSFER_FALLBACK_MESSAGE);

        return $normalized === $needle
            || str_contains($normalized, 'transferring your chat to our available executive');
    }

    private function buildSystemPrompt(AiBot $bot, string $userMessage): string
    {
        $parts = [];

        if (! empty($bot->system_prompt)) {
            $parts[] = $bot->system_prompt;
        }

        if (! empty($bot->business_information)) {
            $parts[] = "Business context: {$bot->business_information}";
        }

        // Legacy MySQL embeddings path is deprecated; only short business_information stays in DB.
        $ragContext = $this->ragService->retrieveContext($bot, $userMessage);
        if (! empty($ragContext)) {
            $parts[] = $ragContext;
        }

        if (empty($parts)) {
            $parts[] = 'You are a helpful customer support assistant for WhatsApp. Be concise and friendly. Keep responses under 300 words.';
        }

        $parts[] = 'Important: Format your response for WhatsApp. Use *bold* for emphasis. Keep it concise and conversational.';

        return implode("\n\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logPythonUsage(AiBot $bot, array $payload, ?int $conversationId = null): void
    {
        $prompt = (int) ($payload['prompt_tokens'] ?? 0);
        $completion = (int) ($payload['completion_tokens'] ?? 0);
        $total = (int) ($payload['total_tokens'] ?? $payload['tokens'] ?? ($prompt + $completion));

        if ($total <= 0 && $prompt + $completion <= 0) {
            return;
        }

        $config = $bot->resolveProvider();
        $provider = $payload['provider']
            ?? (is_string($config['provider'] ?? null)
                ? $config['provider']
                : (string) ($config['provider']->value ?? $config['provider'] ?? $bot->provider));

        $this->tokenUsageService->log([
            'ai_bot_id' => $bot->id,
            'provider' => is_string($provider) ? $provider : (string) $provider,
            'model' => (string) ($payload['model'] ?? $config['chat_model'] ?? 'unknown'),
            'request_type' => 'chat',
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total > 0 ? $total : ($prompt + $completion),
            'conversation_id' => $conversationId,
        ]);
    }
}
