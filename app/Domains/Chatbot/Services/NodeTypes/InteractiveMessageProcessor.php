<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
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
        $interactiveType = (string) ($data['interactiveType'] ?? $data['interactive_type'] ?? 'button');
        $variables = $state->variables ?? [];

        if ($this->hasApiInteractivePayload($data)) {
            $content = $this->resolveInteractiveContent($data, $variables);
            $previewBody = (string) ($content['body']['text'] ?? '[Interactive message]');
            $this->sendInteractive($conversation, $content, $previewBody);

            if ($interactiveType === 'flow' || ($content['type'] ?? '') === 'flow') {
                $state->mergeVariables([
                    '_whatsapp_flow_id' => (string) ($content['action']['parameters']['flow_id'] ?? ''),
                    '_whatsapp_flow_token' => (string) ($content['action']['parameters']['flow_token'] ?? ''),
                    '_whatsapp_flow_node_id' => (string) ($node['id'] ?? ''),
                ]);
            } else {
                $options = $this->buildOptions($data, $interactiveType);
                $state->mergeVariables([
                    '_interactive_node_id' => $node['id'] ?? '',
                    '_interactive_options' => $options,
                    '_interactive_type' => $interactiveType,
                ]);
            }

            $state->forceFill([
                'status' => ChatbotFlowStateStatus::Waiting,
                'current_node_id' => (string) ($node['id'] ?? ''),
            ])->save();

            return NodeProcessResult::WaitForResponse;
        }

        $bodyText = (string) ($data['bodyText'] ?? $data['text'] ?? '');

        if ($bodyText !== '') {
            $resolved = $this->resolveText($bodyText, $variables);
            $this->sendText($conversation, $resolved);
        }

        if ($interactiveType === 'flow') {
            $flowId = (string) ($data['flow_id'] ?? $data['flowId'] ?? '');
            $flowCta = (string) ($data['flow_cta'] ?? $data['flowCta'] ?? 'Open');
            $flow = $flowId !== '' ? $this->flowInteractiveService->findByIdentifier($flowId) : null;

            if ($flow !== null) {
                $content = $this->flowInteractiveService->buildFlowInteractiveContent(
                    $flow,
                    $bodyText !== '' ? $this->resolveText($bodyText, $variables) : 'Tap below to continue',
                    $flowCta,
                    filled($data['flow_token'] ?? $data['flowToken'] ?? null)
                        ? (string) ($data['flow_token'] ?? $data['flowToken'])
                        : null,
                );

                $this->sendInteractive($conversation, $content, (string) ($content['body']['text'] ?? null));

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
        }

        $options = $this->buildOptions($data, $interactiveType);

        if ($options !== []) {
            $messageBody = $this->formatOptionsAsText($options, $interactiveType);
            $this->sendText($conversation, $messageBody);
        }

        $state->mergeVariables([
            '_interactive_node_id' => $node['id'] ?? '',
            '_interactive_options' => $options,
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
     */
    private function hasApiInteractivePayload(array $data): bool
    {
        return isset($data['action'], $data['type']);
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

        if (($content['type'] ?? '') === 'flow') {
            $parameters = $content['action']['parameters'] ?? null;

            if (is_array($parameters) && blank($parameters['flow_token'] ?? null) && filled($parameters['flow_id'] ?? null)) {
                $parameters['flow_token'] = $this->flowInteractiveService->generateFlowToken((string) $parameters['flow_id']);
                $content['action']['parameters'] = $parameters;
            }
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{id: string, title: string}>
     */
    private function buildOptions(array $data, string $interactiveType): array
    {
        $options = [];

        if ($interactiveType === 'button') {
            $buttons = $data['buttons'] ?? $data['options'] ?? [];

            foreach ($buttons as $index => $button) {
                $options[] = [
                    'id' => (string) ($button['id'] ?? "btn_{$index}"),
                    'title' => (string) ($button['title'] ?? $button['text'] ?? $button['label'] ?? ''),
                ];
            }
        } else {
            $sections = $data['sections'] ?? $data['listItems'] ?? [];

            foreach ($sections as $section) {
                $rows = $section['rows'] ?? $section['items'] ?? [];

                foreach ($rows as $index => $row) {
                    $options[] = [
                        'id' => (string) ($row['id'] ?? "list_{$index}"),
                        'title' => (string) ($row['title'] ?? $row['text'] ?? $row['label'] ?? ''),
                    ];
                }
            }
        }

        return $options;
    }

    /**
     * @param  array<int, array{id: string, title: string}>  $options
     */
    private function formatOptionsAsText(array $options, string $type): string
    {
        if ($type === 'button') {
            $labels = array_map(fn (array $o): string => "[{$o['title']}]", $options);

            return implode('  ', $labels);
        }

        $lines = [];

        foreach ($options as $i => $option) {
            $lines[] = ($i + 1).'. '.$option['title'];
        }

        return implode("\n", $lines);
    }
}
