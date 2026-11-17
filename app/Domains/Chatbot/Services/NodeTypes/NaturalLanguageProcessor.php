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

        $lineId = $conversation->whatsapp_line_id !== null
            ? (int) $conversation->whatsapp_line_id
            : null;

        /** @var AiBot|null $bot */
        $bot = null;
        if ($botId) {
            $bot = AiBot::query()->find($botId);
            // Ignore a bot pinned in the flow if it belongs to another WhatsApp number.
            if ($bot !== null && $lineId !== null && $bot->whatsapp_line_id !== null
                && (int) $bot->whatsapp_line_id !== $lineId) {
                $bot = null;
            }
        }

        if ($bot === null) {
            $hasLineBots = $lineId !== null
                && AiBot::query()->active()->where('whatsapp_line_id', $lineId)->exists();

            $forLine = static function () use ($lineId, $hasLineBots) {
                $query = AiBot::query()->active();
                if ($lineId === null) {
                    return $query;
                }

                if ($hasLineBots) {
                    // Dedicated bots exist for this number — never fall back to other/legacy bots.
                    $query->where('whatsapp_line_id', $lineId);
                } else {
                    $query->where(function ($q) use ($lineId): void {
                        $q->where('whatsapp_line_id', $lineId)->orWhereNull('whatsapp_line_id');
                    });
                }

                return $query;
            };

            $bot = $lineId !== null
                ? $forLine()->where('is_default', true)->where('whatsapp_line_id', $lineId)->first()
                : null;
            $bot ??= $forLine()->where('is_default', true)->first();
            $bot ??= $lineId !== null
                ? $forLine()->where('whatsapp_line_id', $lineId)->first()
                : null;
            $bot ??= $forLine()->first();
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
                // Custom prompt must REPLACE the bot's stored prompt (append was ignored by RAG
                // reload and lost to booking CTAs baked into the default bot / business info).
                $customPrompt = trim((string) ($data['customPrompt'] ?? $data['custom_prompt'] ?? ''));
                $promptOverride = $customPrompt !== ''
                    ? $this->resolveText($customPrompt, $variables, $conversation)
                    : null;

                if ($promptOverride !== null && $promptOverride !== '') {
                    $bot->system_prompt = $promptOverride;
                    $bot->business_information = null;
                }

                $blockBookingLinks = array_key_exists('blockBookingLinks', $data)
                    ? (bool) $data['blockBookingLinks']
                    : (array_key_exists('block_booking_links', $data)
                        ? (bool) $data['block_booking_links']
                        : $promptOverride !== null && $promptOverride !== '');

                $sendDirectly = ! isset($data['sendDirectly']) || (bool) $data['sendDirectly'];
                // Always use AiChatService (RAG path). sendDirectly=false keeps text in a
                // variable so the next interactive node can show answer + buttons together.
                $responseText = $this->aiChatService->processMessage($conversation, $bot, $userMessage, [
                    'system_prompt_override' => $promptOverride,
                    'strip_booking_links' => $blockBookingLinks,
                    'send' => $sendDirectly,
                ]);

                if (($responseText === null || trim($responseText) === '') && ! $sendDirectly) {
                    $responseText = 'I could not find a clear answer for that. Would you like to chat with an agent?';
                }

                // WhatsApp interactive body limit is 1024; leave room for a short footer line.
                if ($responseText !== null && mb_strlen($responseText) > 900) {
                    $responseText = mb_substr($responseText, 0, 897).'...';
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
