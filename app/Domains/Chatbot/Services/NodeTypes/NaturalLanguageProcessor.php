<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\AiBot\Services\AiChatService;
use App\Domains\AiBot\Services\AiProviderKeyService;
use App\Domains\AiBot\Support\AiResponseFormatter;
use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Support\FlowVariableResolver;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Models\AiBot;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class NaturalLanguageProcessor extends AbstractNodeProcessor
{
    public function __construct(
        InboxOutboundService $outboundService,
        FlowVariableResolver $variableResolver,
        private readonly AiChatService $aiChatService,
        private readonly AiProviderKeyService $providerKeyService,
    ) {
        parent::__construct($outboundService, $variableResolver);
    }

    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $data = $this->nodeData($node);
        $variables = $state->variables ?? [];

        // 1. Determine user input message
        $inputSource = (string) ($data['inputSource'] ?? $data['input_source'] ?? 'last_reply');
        $customInputVar = (string) ($data['inputVariable'] ?? $data['input_variable'] ?? '');

        $userMessage = match ($inputSource) {
            'custom_variable' => (string) ($variables[$customInputVar] ?? ''),
            default => (string) ($variables['_last_reply'] ?? $variables['user_response'] ?? ''),
        };

        if (trim($userMessage) === '') {
            $userMessage = 'Hello';
        }

        $mode = (string) ($data['mode'] ?? 'knowledge_base'); // 'knowledge_base', 'custom_prompt', 'intent_classification'
        $botId = $data['ai_bot_id'] ?? $data['aiBotId'] ?? null;

        /** @var AiBot|null $bot */
        $bot = null;
        if ($botId) {
            $bot = AiBot::query()->find($botId);
        }

        if ($bot === null) {
            $bot = AiBot::query()->where('is_default', true)->first()
                ?? AiBot::query()->where('status', 'active')->first();
        }

        $responseText = null;
        $extractedIntent = null;

        if ($mode === 'intent_classification') {
            $extractedIntent = $this->classifyIntent($userMessage, $data, $bot);
            $variables['_ai_intent'] = $extractedIntent;
            $variables['ai_intent'] = $extractedIntent;
        } else {
            // Generative AI response with Knowledge Base / Custom Prompt
            if ($bot !== null) {
                // If custom prompt override provided, dynamically inject
                $customPrompt = trim((string) ($data['customPrompt'] ?? $data['custom_prompt'] ?? ''));
                if ($customPrompt !== '') {
                    $resolvedPrompt = $this->resolveText($customPrompt, $variables);
                    $bot->system_prompt = $bot->system_prompt
                        ? "{$bot->system_prompt}\n\nAdditional Instructions: {$resolvedPrompt}"
                        : $resolvedPrompt;
                }

                $sendDirectly = ! isset($data['sendDirectly']) || (bool) $data['sendDirectly'];
                if ($sendDirectly) {
                    $responseText = $this->aiChatService->processMessage($conversation, $bot, $userMessage);
                } else {
                    // Generate response without sending directly (for variable storage)
                    $responseText = $this->generateAiResponse($bot, $userMessage);
                }
            } else {
                Log::warning('No AI bot configured for NaturalLanguageProcessor');
            }

            if ($responseText !== null) {
                $outputVar = (string) ($data['outputVariable'] ?? $data['output_variable'] ?? '_ai_response');
                $variables[$outputVar] = $responseText;
                $variables['_ai_response'] = $responseText;
            }
        }

        // Branching resolution
        $nextId = null;

        if ($mode === 'intent_classification' && $extractedIntent !== null) {
            $intentHandle = 'output_intent_' . strtolower($extractedIntent);
            $nextId = $this->nextNodeIdFromHandle($node, $intentHandle)
                ?? $this->nextNodeIdFromHandle($node, strtolower($extractedIntent))
                ?? $this->nextNodeIdFromHandle($node, 'output_fallback')
                ?? $this->defaultNextNodeId($node);
        } else {
            $nextId = $this->defaultNextNodeId($node);
        }

        $state->forceFill([
            'variables' => $variables,
            'current_node_id' => $nextId ?? $state->current_node_id,
        ])->save();

        return $nextId !== null ? NodeProcessResult::Continue : NodeProcessResult::Completed;
    }

    private function generateAiResponse(AiBot $bot, string $userMessage): ?string
    {
        try {
            $config = $bot->resolveProvider();
            if (empty($config['api_key'])) {
                return null;
            }

            $provider = $this->providerKeyService->resolveProvider($bot->provider);
            $messages = [
                ['role' => 'system', 'content' => $bot->system_prompt ?? 'You are a helpful AI assistant.'],
                ['role' => 'user', 'content' => $userMessage],
            ];

            $result = $provider->chat($messages, [
                'api_key' => $config['api_key'],
                'model' => $config['chat_model'],
                'temperature' => $bot->temperature ?? 0.3,
            ]);

            return AiResponseFormatter::forWhatsApp($result['text']);
        } catch (\Throwable $e) {
            Log::error('generateAiResponse failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function classifyIntent(string $userMessage, array $data, ?AiBot $bot): string
    {
        $intents = (array) ($data['intents'] ?? ['sales', 'support', 'pricing', 'general']);
        $intentListStr = implode(', ', $intents);

        // 1. Try LLM classification if bot is configured with API key
        if ($bot !== null) {
            try {
                $config = $bot->resolveProvider();
                if (! empty($config['api_key'])) {
                    $provider = $this->providerKeyService->resolveProvider($bot->provider);
                    $prompt = "Classify the following user message into EXACTLY ONE of these categories: [{$intentListStr}, fallback].\n"
                        ."User message: \"{$userMessage}\"\n"
                        ."Respond ONLY with the category name in lowercase and nothing else.";

                    $messages = [
                        ['role' => 'system', 'content' => 'You are an intent classification engine. Output only the category keyword.'],
                        ['role' => 'user', 'content' => $prompt],
                    ];

                    $result = $provider->chat($messages, [
                        'api_key' => $config['api_key'],
                        'model' => $config['chat_model'],
                        'temperature' => 0.0,
                    ]);

                    $classified = strtolower(trim($result['text']));

                    foreach ($intents as $intent) {
                        if (str_contains($classified, strtolower((string) $intent))) {
                            return (string) $intent;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Intent classification LLM call failed, falling back to keyword matching: '.$e->getMessage());
            }
        }

        // 2. Keyword-based matching fallback
        $lower = strtolower($userMessage);
        foreach ($intents as $intent) {
            if (str_contains($lower, strtolower((string) $intent))) {
                return (string) $intent;
            }
        }

        return 'fallback';
    }
}
