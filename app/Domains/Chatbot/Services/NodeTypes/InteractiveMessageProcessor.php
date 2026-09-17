<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Templates\Support\InteractiveMessagePayloadBuilder;
use App\Domains\WhatsappFlow\Services\WhatsappFlowInteractiveService;
use App\Enums\ChatbotFlowStateStatus;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

class InteractiveMessageProcessor extends AbstractNodeProcessor
{
    public function __construct(
        \App\Domains\Inbox\Services\InboxOutboundService $outboundService,
        \App\Domains\Chatbot\Support\FlowVariableResolver $variableResolver,
        private readonly WhatsappFlowInteractiveService $flowInteractiveService,
        private readonly InteractiveMessagePayloadBuilder $payloadBuilder,
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
        $interactiveType = (string) ($data['interactiveType'] ?? $data['interactive_type'] ?? $data['type'] ?? 'button');
        $variables = $state->variables ?? [];
        $resolve = fn (string $text): string => $this->resolveText($text, $variables);

        $content = $this->payloadBuilder->fromNodeData($data, $resolve);

        if ($content === null && ($interactiveType === 'flow' || ($data['type'] ?? '') === 'flow')) {
            $bodyText = (string) ($data['bodyText'] ?? $data['text'] ?? '');
            $flowId = (string) ($data['flow_id'] ?? $data['flowId'] ?? '');
            $flowCta = (string) ($data['flow_cta'] ?? $data['flowCta'] ?? 'Open');
            $flow = $flowId !== '' ? $this->flowInteractiveService->findByIdentifier($flowId) : null;

            if ($flow !== null) {
                $content = $this->flowInteractiveService->buildFlowInteractiveContent(
                    $flow,
                    $bodyText !== '' ? $resolve($bodyText) : 'Tap below to continue',
                    $flowCta,
                    filled($data['flow_token'] ?? $data['flowToken'] ?? null)
                        ? (string) ($data['flow_token'] ?? $data['flowToken'])
                        : null,
                );
            }
        }

        if ($content !== null) {
            $previewBody = (string) ($content['body']['text'] ?? '[Interactive message]');
            $this->sendInteractive($conversation, $content, $previewBody);

            if (($content['type'] ?? '') === 'flow' || $interactiveType === 'flow') {
                $state->mergeVariables([
                    '_whatsapp_flow_id' => (string) ($content['action']['parameters']['flow_id'] ?? ''),
                    '_whatsapp_flow_token' => (string) ($content['action']['parameters']['flow_token'] ?? ''),
                    '_whatsapp_flow_node_id' => (string) ($node['id'] ?? ''),
                ]);
            } else {
                $state->mergeVariables([
                    '_interactive_node_id' => $node['id'] ?? '',
                    '_interactive_options' => $this->buildOptions($data, $content, $interactiveType),
                    '_interactive_type' => $interactiveType,
                ]);
            }

            $state->forceFill([
                'status' => ChatbotFlowStateStatus::Waiting,
                'current_node_id' => (string) ($node['id'] ?? ''),
            ])->save();

            return NodeProcessResult::WaitForResponse;
        }

        // Last-resort: body text only (no interactive options available).
        $bodyText = (string) ($data['bodyText'] ?? $data['text'] ?? '');
        if ($bodyText !== '') {
            $this->sendText($conversation, $resolve($bodyText));
        }

        $state->mergeVariables([
            '_interactive_node_id' => $node['id'] ?? '',
            '_interactive_options' => [],
            '_interactive_type' => $interactiveType,
        ]);

        $state->forceFill([
            'status' => ChatbotFlowStateStatus::Waiting,
            'current_node_id' => (string) ($node['id'] ?? ''),
        ])->save();

        return NodeProcessResult::WaitForResponse;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $content
     * @return array<int, array{id: string, title: string}>
     */
    private function buildOptions(array $data, array $content, string $interactiveType): array
    {
        $options = [];

        if (($content['type'] ?? $interactiveType) === 'button') {
            foreach ($content['action']['buttons'] ?? [] as $index => $button) {
                if (! is_array($button)) {
                    continue;
                }
                $options[] = [
                    'id' => (string) ($button['reply']['id'] ?? "btn_{$index}"),
                    'title' => (string) ($button['reply']['title'] ?? ''),
                ];
            }

            if ($options !== []) {
                return $options;
            }
        }

        if (($content['type'] ?? $interactiveType) === 'list') {
            foreach ($content['action']['sections'] ?? [] as $section) {
                if (! is_array($section)) {
                    continue;
                }
                foreach ($section['rows'] ?? [] as $index => $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $options[] = [
                        'id' => (string) ($row['id'] ?? "list_{$index}"),
                        'title' => (string) ($row['title'] ?? ''),
                    ];
                }
            }

            if ($options !== []) {
                return $options;
            }
        }

        // Fallback to raw node buttons/sections
        if ($interactiveType === 'button') {
            foreach ($data['buttons'] ?? $data['options'] ?? [] as $index => $button) {
                if (is_string($button)) {
                    $options[] = ['id' => "btn_{$index}", 'title' => $button];

                    continue;
                }
                if (! is_array($button)) {
                    continue;
                }
                $options[] = [
                    'id' => (string) ($button['id'] ?? "btn_{$index}"),
                    'title' => (string) ($button['title'] ?? $button['text'] ?? $button['label'] ?? ''),
                ];
            }
        } else {
            foreach ($data['sections'] ?? $data['listItems'] ?? [] as $section) {
                if (! is_array($section)) {
                    continue;
                }
                foreach ($section['rows'] ?? $section['items'] ?? [] as $index => $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $options[] = [
                        'id' => (string) ($row['id'] ?? "list_{$index}"),
                        'title' => (string) ($row['title'] ?? $row['text'] ?? $row['label'] ?? ''),
                    ];
                }
            }
        }

        return $options;
    }
}
