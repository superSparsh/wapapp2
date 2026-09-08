<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\WhatsappFlow\Services\WhatsappFlowInteractiveService;
use App\Enums\ChatbotFlowStateStatus;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use App\Models\Template;

class WhatsappFlowTemplateProcessor extends AbstractNodeProcessor
{
    public function __construct(
        \App\Domains\Inbox\Services\InboxOutboundService $outboundService,
        \App\Domains\Chatbot\Support\FlowVariableResolver $variableResolver,
        private readonly WhatsappFlowInteractiveService $flowInteractiveService,
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
        $templateId = $data['templateId'] ?? $data['selectedTemplate']['id'] ?? null;

        if ($templateId !== null && $templateId !== '') {
            $template = Template::query()->find($templateId);
            $templateCode = $template?->whatsappCode();

            if ($templateCode !== null && $templateCode !== '') {
                $this->sendTemplate($conversation, $templateCode);

                $state->mergeVariables([
                    '_whatsapp_flow_template_id' => (string) $templateId,
                    '_whatsapp_flow_node_id' => (string) ($node['id'] ?? ''),
                ]);

                $state->forceFill([
                    'status' => ChatbotFlowStateStatus::Waiting,
                    'current_node_id' => (string) ($node['id'] ?? ''),
                ])->save();

                return NodeProcessResult::WaitForResponse;
            }
        }

        if ($this->hasApiInteractivePayload($data)) {
            $content = $this->resolveInteractiveContent($data, $variables);
            $this->sendInteractive($conversation, $content);

            $state->mergeVariables([
                '_whatsapp_flow_id' => (string) ($content['action']['parameters']['flow_id'] ?? $data['flowId'] ?? ''),
                '_whatsapp_flow_token' => (string) ($content['action']['parameters']['flow_token'] ?? $data['flowToken'] ?? ''),
                '_whatsapp_flow_node_id' => (string) ($node['id'] ?? ''),
            ]);

            $state->forceFill([
                'status' => ChatbotFlowStateStatus::Waiting,
                'current_node_id' => (string) ($node['id'] ?? ''),
            ])->save();

            return NodeProcessResult::WaitForResponse;
        }

        $flowId = (string) ($data['flowId'] ?? $data['flow_id'] ?? '');
        $flowCta = (string) ($data['flowCta'] ?? $data['flow_cta'] ?? $data['ctaText'] ?? 'Open');
        $bodyText = $this->resolveText((string) ($data['bodyText'] ?? ''), $variables);
        $flow = $flowId !== '' ? $this->flowInteractiveService->findByIdentifier($flowId) : null;

        if ($flow !== null) {
            $content = $this->flowInteractiveService->buildFlowInteractiveContent(
                $flow,
                $bodyText !== '' ? $bodyText : 'Tap below to continue',
                $flowCta,
                filled($data['flowToken'] ?? $data['flow_token'] ?? null)
                    ? (string) ($data['flowToken'] ?? $data['flow_token'])
                    : null,
            );

            $this->sendInteractive($conversation, $content);

            $state->mergeVariables([
                '_whatsapp_flow_id' => (string) ($content['action']['parameters']['flow_id'] ?? $flowId),
                '_whatsapp_flow_token' => (string) ($content['action']['parameters']['flow_token'] ?? ''),
                '_whatsapp_flow_node_id' => (string) ($node['id'] ?? ''),
            ]);

            $state->forceFill([
                'status' => ChatbotFlowStateStatus::Waiting,
                'current_node_id' => (string) ($node['id'] ?? ''),
            ])->save();

            return NodeProcessResult::WaitForResponse;
        }

        $headerText = $this->resolveText((string) ($data['headerText'] ?? ''), $variables);
        $messageParts = array_filter([$headerText, $bodyText]);

        if ($messageParts !== []) {
            $this->sendText($conversation, implode("\n", $messageParts));
        }

        if ($flowCta !== '') {
            $this->sendText($conversation, "[{$flowCta}]");
        }

        return NodeProcessResult::WaitForResponse;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasApiInteractivePayload(array $data): bool
    {
        return isset($data['action'], $data['type']) && (string) $data['type'] === 'flow';
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function resolveInteractiveContent(array $data, array $variables): array
    {
        $content = [
            'type' => (string) $data['type'],
        ];

        foreach (['header', 'body', 'footer'] as $section) {
            if (! isset($data[$section]) || ! is_array($data[$section])) {
                continue;
            }

            $sectionData = $data[$section];

            if (isset($sectionData['text']) && is_string($sectionData['text'])) {
                $sectionData['text'] = $this->resolveText($sectionData['text'], $variables);
            }

            $content[$section] = $sectionData;
        }

        $content['action'] = is_array($data['action']) ? $data['action'] : [];

        $parameters = $content['action']['parameters'] ?? null;

        if (is_array($parameters)) {
            if (blank($parameters['flow_token'] ?? null) && filled($parameters['flow_id'] ?? null)) {
                $parameters['flow_token'] = $this->flowInteractiveService->generateFlowToken((string) $parameters['flow_id']);
            }

            $content['action']['parameters'] = $parameters;
        }

        return $content;
    }
}
