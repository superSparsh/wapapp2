<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Support\FlowVariableResolver;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

abstract class AbstractNodeProcessor implements NodeProcessorInterface
{
    public function __construct(
        protected readonly InboxOutboundService $outboundService,
        protected readonly FlowVariableResolver $variableResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    protected function nodeData(array $node): array
    {
        return $node['data'] ?? [];
    }

    /**
     * Resolve the next node ID from a specific output handle.
     *
     * @param  array<string, mixed>  $node
     */
    protected function nextNodeIdFromHandle(array $node, string $handle): ?string
    {
        $connections = $node['outputs'][$handle]['connections'] ?? [];

        return isset($connections[0]['node']) ? (string) $connections[0]['node'] : null;
    }

    /**
     * Resolve the default next node ID (legacy: only `default` / `output_1`).
     * Never fall through to reply-*, button-*, unread, etc. — those are branch handles.
     *
     * @param  array<string, mixed>  $node
     */
    protected function defaultNextNodeId(array $node): ?string
    {
        foreach (['default', 'output_1'] as $handle) {
            $nextId = $this->nextNodeIdFromHandle($node, $handle);
            if ($nextId !== null) {
                return $nextId;
            }
        }

        return null;
    }

    /**
     * Resolve variables in a text string (supports $(var) and {{var}}).
     *
     * @param  array<string, mixed>  $variables
     */
    protected function resolveText(string $text, array $variables, ?Conversation $conversation = null): string
    {
        if ($conversation !== null) {
            $variables = $this->variableResolver->withConversationContext($variables, $conversation);
        }

        return $this->variableResolver->resolve($text, $variables);
    }

    /**
     * Send a text message via the outbound service.
     */
    protected function sendText(Conversation $conversation, string $body): void
    {
        if (trim($body) === '') {
            return;
        }

        $this->outboundService->sendText($conversation, $body, enforceWindow: false);
    }

    /**
     * Send a template message via the outbound service.
     *
     * @param  array<string, mixed>  $params
     */
    protected function sendTemplate(Conversation $conversation, string $templateCode, array $params = []): void
    {
        if (trim($templateCode) === '') {
            return;
        }

        $this->outboundService->sendTemplate($conversation, $templateCode, $params);
    }

    /**
     * @param  array<string, mixed>  $interactiveContent
     */
    protected function sendInteractive(Conversation $conversation, array $interactiveContent, ?string $previewBody = null): void
    {
        if ($interactiveContent === []) {
            return;
        }

        $this->outboundService->sendInteractive($conversation, $interactiveContent, $previewBody, enforceWindow: false);
    }

    /**
     * Send real WhatsApp typing indicator.
     */
    protected function sendTypingIndicator(Conversation $conversation): void
    {
        $this->outboundService->sendTypingIndicator($conversation);
    }
}
