<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Support\FlowVariableResolver;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use App\Models\Template;

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
     * If those are missing, use the sole non-branch outbound edge (Loose-mode saves).
     *
     * @param  array<string, mixed>  $node
     */
    protected function defaultNextNodeId(array $node): ?string
    {
        foreach (['default', 'output_1', 'output_2'] as $handle) {
            $nextId = $this->nextNodeIdFromHandle($node, $handle);
            if ($nextId !== null) {
                return $nextId;
            }
        }

        return $this->firstLinearNextNodeId($node);
    }

    /**
     * First outbound connection that is not a reply/button/status branch handle.
     *
     * @param  array<string, mixed>  $node
     */
    protected function firstLinearNextNodeId(array $node): ?string
    {
        $outputs = is_array($node['outputs'] ?? null) ? $node['outputs'] : [];

        foreach ($outputs as $handle => $output) {
            $handle = (string) $handle;
            if ($this->isBranchHandle($handle)) {
                continue;
            }

            if (! is_array($output)) {
                continue;
            }

            $connections = $output['connections'] ?? [];
            if (isset($connections[0]['node']) && (string) $connections[0]['node'] !== '') {
                return (string) $connections[0]['node'];
            }
        }

        return null;
    }

    protected function isBranchHandle(string $handle): bool
    {
        $handle = strtolower(trim($handle));

        if (in_array($handle, [
            'unread', 'undelivered', 'delivered',
            'open', 'closed', 'output_open', 'output_closed',
            'yes', 'no', 'output_yes', 'output_no',
            'true', 'false', 'output_true', 'output_false',
        ], true)) {
            return true;
        }

        foreach (['reply-', 'reply_', 'button-', 'interactive-', 'carousel-'] as $prefix) {
            if (str_starts_with($handle, $prefix)) {
                return true;
            }
        }

        return false;
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
     * Resolve the CAMS TemplateCode from node data.
     * Builder stores DB `templateId`; WhatsApp needs the provider TemplateCode.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveTemplateSendCode(array $data): string
    {
        $selected = is_array($data['selectedTemplate'] ?? null) ? $data['selectedTemplate'] : [];

        $candidates = [
            $data['templateCode'] ?? null,
            $data['template_code'] ?? null,
            $selected['template_code'] ?? null,
            $selected['code'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '' && CamsTemplateIdentity::isProviderCode($value)) {
                return $value;
            }
        }

        $dbId = $data['templateId'] ?? $selected['id'] ?? null;
        if (filled($dbId) && is_numeric($dbId)) {
            $template = Template::query()->find((int) $dbId);
            if ($template !== null) {
                $provider = $template->whatsappCode();
                if (filled($provider)) {
                    return (string) $provider;
                }
            }
        }

        // Legacy nodes sometimes stored the provider code in templateId.
        $fallbackId = trim((string) ($data['templateId'] ?? $data['template_name'] ?? ''));
        if ($fallbackId !== '' && CamsTemplateIdentity::isProviderCode($fallbackId)) {
            return $fallbackId;
        }

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '' && $value !== '0') {
                return $value;
            }
        }

        return ($fallbackId !== '' && $fallbackId !== '0') ? $fallbackId : '';
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

        $this->outboundService->sendTemplate(
            $conversation,
            $templateCode,
            $params,
            extraMetadata: [
                'wallet_source' => 'chatbot',
                'billable' => true,
            ],
        );
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
