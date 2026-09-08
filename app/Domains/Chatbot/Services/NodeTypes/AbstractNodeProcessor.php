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
     * Resolve the default next node ID (output_1 or first available).
     *
     * @param  array<string, mixed>  $node
     */
    protected function defaultNextNodeId(array $node): ?string
    {
        // Try output_1 first, then 'default'
        $nextId = $this->nextNodeIdFromHandle($node, 'output_1');

        if ($nextId !== null) {
            return $nextId;
        }

        $nextId = $this->nextNodeIdFromHandle($node, 'default');

        if ($nextId !== null) {
            return $nextId;
        }

        // Fall back to first available output
        $outputs = $node['outputs'] ?? [];

        foreach ($outputs as $output) {
            $connections = $output['connections'] ?? [];

            if (isset($connections[0]['node'])) {
                return (string) $connections[0]['node'];
            }
        }

        return null;
    }

    /**
     * Resolve variables in a text string.
     *
     * @param  array<string, mixed>  $variables
     */
    protected function resolveText(string $text, array $variables): string
    {
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
