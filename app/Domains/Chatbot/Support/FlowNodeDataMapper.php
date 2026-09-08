<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Support;

/**
 * Maps chatbot builder field names to engine/runtime field names (and back for the editor).
 */
class FlowNodeDataMapper
{
    /**
     * @param  array{nodes?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $flowData
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function prepareForStorage(array $flowData): array
    {
        $nodes = [];

        foreach ($flowData['nodes'] ?? [] as $node) {
            if (! is_array($node)) {
                continue;
            }

            $type = (string) ($node['type'] ?? '');
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];

            $nodes[] = array_merge($node, [
                'data' => $this->syncNodeData($type, $data),
            ]);
        }

        return [
            'nodes' => $nodes,
            'edges' => is_array($flowData['edges'] ?? null) ? $flowData['edges'] : [],
        ];
    }

    /**
     * @param  array{nodes?: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}|null  $flowData
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function prepareForEditor(?array $flowData): array
    {
        if ($flowData === null || $flowData === []) {
            return ['nodes' => [], 'edges' => []];
        }

        return $this->prepareForStorage($flowData);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncNodeData(string $type, array $data): array
    {
        return match ($type) {
            'welcomeMessage' => $this->syncWelcomeMessage($data),
            'interactiveMessage' => $this->syncInteractiveMessage($data),
            'templateMessage' => $this->syncTemplateMessage($data),
            'mediaMessage' => $this->syncMediaMessage($data),
            'waitForResponse' => $this->syncWaitForResponse($data),
            'delay', 'typingIndicator' => $this->syncDelay($data),
            'condition', 'enhancedCondition' => $this->syncCondition($data),
            'dateTimeCondition' => $this->syncDateTimeCondition($data),
            'httpRequest' => $this->syncHttpRequest($data),
            'functionCall' => $this->syncFunctionCall($data),
            'jumpToStep' => $this->syncJumpToStep($data),
            'naturalLanguage' => $this->syncNaturalLanguage($data),
            default => $data,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncWelcomeMessage(array $data): array
    {
        $text = (string) ($data['text'] ?? $data['message'] ?? '');
        $keyword = (string) ($data['triggerKeyword'] ?? $data['keywords'] ?? '');

        $data['text'] = $text;
        $data['message'] = $text;
        $data['triggerKeyword'] = $keyword;
        $data['keywords'] = $keyword;
        $data['messageType'] = (string) ($data['messageType'] ?? 'text');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncInteractiveMessage(array $data): array
    {
        $body = (string) ($data['bodyText'] ?? $data['text'] ?? $data['message'] ?? '');
        $interactiveType = strtolower((string) ($data['interactiveType'] ?? $data['interactive_type'] ?? 'button'));
        $buttons = $this->normalizeButtons($data);

        $data['bodyText'] = $body;
        $data['text'] = $body;
        $data['message'] = $body;
        $data['interactiveType'] = $interactiveType;
        $data['interactive_type'] = $interactiveType;
        $data['buttons'] = $buttons;
        $data['options'] = array_column($buttons, 'title');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncTemplateMessage(array $data): array
    {
        $code = (string) ($data['templateCode'] ?? $data['templateId'] ?? $data['template_name'] ?? '');
        $keyword = (string) ($data['triggerKeyword'] ?? $data['keywords'] ?? '');

        $data['template_name'] = $code;
        $data['templateCode'] = $code;
        $data['templateId'] = $code;
        $data['triggerKeyword'] = $keyword;
        $data['keywords'] = $keyword;
        $data['messageType'] = $code !== '' ? 'template' : (string) ($data['messageType'] ?? 'text');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncMediaMessage(array $data): array
    {
        $caption = (string) ($data['text'] ?? $data['message'] ?? $data['caption'] ?? '');
        $url = (string) ($data['mediaUrl'] ?? $data['media_url'] ?? '');
        $type = (string) ($data['mediaType'] ?? $data['media_type'] ?? 'image');

        $data['text'] = $caption;
        $data['message'] = $caption;
        $data['caption'] = $caption;
        $data['mediaUrl'] = $url;
        $data['media_url'] = $url;
        $data['mediaType'] = $type;
        $data['media_type'] = $type;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncWaitForResponse(array $data): array
    {
        $variable = (string) ($data['variableName'] ?? $data['variable_name'] ?? 'user_response');

        $data['variableName'] = $variable;
        $data['variable_name'] = $variable;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncDelay(array $data): array
    {
        $seconds = (int) ($data['delay_seconds'] ?? $data['delaySeconds'] ?? $data['duration'] ?? 1);

        if ($seconds < 1) {
            $seconds = 1;
        }

        $data['delay_seconds'] = $seconds;
        $data['delaySeconds'] = $seconds;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncCondition(array $data): array
    {
        $variable = (string) ($data['condition_variable'] ?? $data['variable'] ?? '');
        $operator = (string) ($data['condition_operator'] ?? $data['operator'] ?? 'equals');
        $value = (string) ($data['condition_value'] ?? $data['value'] ?? '');

        $data['condition_variable'] = $variable;
        $data['variable'] = $variable;
        $data['condition_operator'] = $operator;
        $data['operator'] = $operator;
        $data['condition_value'] = $value;
        $data['value'] = $value;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncHttpRequest(array $data): array
    {
        $data['method'] = strtoupper((string) ($data['method'] ?? 'GET'));

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncFunctionCall(array $data): array
    {
        $name = (string) ($data['function_name'] ?? $data['functionName'] ?? '');

        $data['function_name'] = $name;
        $data['functionName'] = $name;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncDateTimeCondition(array $data): array
    {
        $timezone = (string) ($data['timezone'] ?? config('app.timezone', 'Asia/Kolkata'));
        $data['timezone'] = $timezone;
        $data['mode'] = (string) ($data['mode'] ?? $data['conditionType'] ?? $data['condition_type'] ?? 'business_hours');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncNaturalLanguage(array $data): array
    {
        $data['mode'] = (string) ($data['mode'] ?? 'knowledge_base');
        $data['output_variable'] = (string) ($data['outputVariable'] ?? $data['output_variable'] ?? '_ai_response');
        $data['custom_prompt'] = (string) ($data['customPrompt'] ?? $data['custom_prompt'] ?? '');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncJumpToStep(array $data): array
    {
        $target = (string) ($data['targetNodeId'] ?? $data['target_node'] ?? $data['targetNode'] ?? $data['targetStep'] ?? $data['stepId'] ?? '');

        $data['target_node'] = $target;
        $data['targetNode'] = $target;
        $data['targetNodeId'] = $target;
        $data['stepId'] = $target;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{id: string, title: string}>
     */
    private function normalizeButtons(array $data): array
    {
        $raw = $data['buttons'] ?? $data['options'] ?? [];
        $buttons = [];

        foreach ($raw as $index => $item) {
            if (is_string($item) && $item !== '') {
                $buttons[] = [
                    'id' => "btn_{$index}",
                    'title' => $item,
                ];

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $title = (string) ($item['title'] ?? $item['text'] ?? $item['label'] ?? '');

            if ($title === '') {
                continue;
            }

            $buttons[] = [
                'id' => (string) ($item['id'] ?? "btn_{$index}"),
                'title' => $title,
            ];
        }

        return $buttons;
    }
}
