<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Services\NodeTypes\CarouselTemplateProcessor;
use App\Domains\Chatbot\Services\NodeTypes\ConditionProcessor;
use App\Domains\Chatbot\Services\NodeTypes\DateTimeConditionProcessor;
use App\Domains\Chatbot\Services\NodeTypes\DelayProcessor;
use App\Domains\Chatbot\Services\NodeTypes\EnhancedConditionProcessor;
use App\Domains\Chatbot\Services\NodeTypes\FunctionCallProcessor;
use App\Domains\Chatbot\Services\NodeTypes\HttpRequestProcessor;
use App\Domains\Chatbot\Services\NodeTypes\InteractiveMessageProcessor;
use App\Domains\Chatbot\Services\NodeTypes\JumpToStepProcessor;
use App\Domains\Chatbot\Services\NodeTypes\MediaMessageProcessor;
use App\Domains\Chatbot\Services\NodeTypes\NaturalLanguageProcessor;
use App\Domains\Chatbot\Services\NodeTypes\NodeProcessorInterface;
use App\Domains\Chatbot\Services\NodeTypes\TemplateMessageProcessor;
use App\Domains\Chatbot\Services\NodeTypes\TypingIndicatorProcessor;
use App\Domains\Chatbot\Services\NodeTypes\WaitForResponseProcessor;
use App\Domains\Chatbot\Services\NodeTypes\WelcomeMessageProcessor;
use App\Domains\Chatbot\Services\NodeTypes\WhatsappFlowTemplateProcessor;
use App\Enums\ChatbotNodeType;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class ChatbotNodeProcessor
{
    /**
     * @var array<string, class-string<NodeProcessorInterface>>
     */
    private array $processorMap = [
        ChatbotNodeType::WelcomeMessage->value => WelcomeMessageProcessor::class,
        ChatbotNodeType::InteractiveMessage->value => InteractiveMessageProcessor::class,
        ChatbotNodeType::MediaMessage->value => MediaMessageProcessor::class,
        ChatbotNodeType::WaitForResponse->value => WaitForResponseProcessor::class,
        ChatbotNodeType::Delay->value => DelayProcessor::class,
        ChatbotNodeType::Condition->value => ConditionProcessor::class,
        ChatbotNodeType::EnhancedCondition->value => EnhancedConditionProcessor::class,
        ChatbotNodeType::DateTimeCondition->value => DateTimeConditionProcessor::class,
        ChatbotNodeType::FunctionCall->value => FunctionCallProcessor::class,
        ChatbotNodeType::HttpRequest->value => HttpRequestProcessor::class,
        ChatbotNodeType::TemplateMessage->value => TemplateMessageProcessor::class,
        ChatbotNodeType::WhatsappFlowTemplate->value => WhatsappFlowTemplateProcessor::class,
        ChatbotNodeType::CarouselTemplate->value => CarouselTemplateProcessor::class,
        ChatbotNodeType::TypingIndicator->value => TypingIndicatorProcessor::class,
        ChatbotNodeType::JumpToStep->value => JumpToStepProcessor::class,
        ChatbotNodeType::NaturalLanguage->value => NaturalLanguageProcessor::class,
    ];

    /**
     * Process a node by delegating to the appropriate processor.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, array<string, mixed>>  $nodeMap
     */
    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $nodeType = (string) ($node['class'] ?? $node['type'] ?? 'unknown');

        $processorClass = $this->processorMap[$nodeType] ?? null;

        if ($processorClass === null) {
            $this->logDebug("No processor for node type: {$nodeType}");

            return NodeProcessResult::Error;
        }

        /** @var NodeProcessorInterface $processor */
        $processor = app($processorClass);

        try {
            return $processor->process($node, $nodeMap, $conversation, $state);
        } catch (\Throwable $e) {
            Log::error('Chatbot node processing error', [
                'node_id' => $node['id'] ?? null,
                'node_type' => $nodeType,
                'error' => $e->getMessage(),
            ]);

            return NodeProcessResult::Error;
        }
    }

    private function logDebug(string $message): void
    {
        if (config('chatbot.debug')) {
            Log::debug("[ChatbotEngine] {$message}");
        }
    }
}
