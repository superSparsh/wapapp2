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

        $edges = is_array($flowData['edges'] ?? null) ? $flowData['edges'] : [];
        $edges = $this->normalizeDateTimeConditionEdges($nodes, $edges);

        return array_merge($flowData, [
            'nodes' => $nodes,
            'edges' => $edges,
        ]);
    }

    /**
     * Persist open/closed handles for Business Hours edges even when the builder
     * saved null/"default" (React Flow Loose mode).
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDateTimeConditionEdges(array $nodes, array $edges): array
    {
        $bhIds = [];
        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            if ($id !== '' && (string) ($node['type'] ?? '') === 'dateTimeCondition') {
                $bhIds[$id] = true;
            }
        }

        if ($bhIds === []) {
            return $edges;
        }

        $namedOpen = ['open', 'output_open', 'yes', 'output_yes', 'true', 'output_true'];
        $namedClosed = ['closed', 'output_closed', 'no', 'output_no', 'false', 'output_false'];

        /** @var array<string, list<int>> $indexesBySource */
        $indexesBySource = [];
        foreach ($edges as $index => $edge) {
            if (! is_array($edge)) {
                continue;
            }
            $source = (string) ($edge['source'] ?? '');
            if ($source === '' || ! isset($bhIds[$source])) {
                continue;
            }
            $indexesBySource[$source][] = $index;
        }

        foreach ($indexesBySource as $indexes) {
            $hasOpen = false;
            $hasClosed = false;
            $unlabeled = [];

            foreach ($indexes as $index) {
                $handle = strtolower(trim((string) ($edges[$index]['sourceHandle'] ?? '')));
                if (in_array($handle, $namedOpen, true)) {
                    $hasOpen = true;
                    $edges[$index]['sourceHandle'] = 'open';
                } elseif (in_array($handle, $namedClosed, true)) {
                    $hasClosed = true;
                    $edges[$index]['sourceHandle'] = 'closed';
                } else {
                    $unlabeled[] = $index;
                }
            }

            foreach ($unlabeled as $index) {
                if (! $hasOpen) {
                    $edges[$index]['sourceHandle'] = 'open';
                    $hasOpen = true;
                } elseif (! $hasClosed) {
                    $edges[$index]['sourceHandle'] = 'closed';
                    $hasClosed = true;
                }
            }
        }

        return array_values($edges);
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
            'welcomeMessage', 'textMessage' => $this->syncWelcomeMessage($data),
            'interactiveMessage' => $this->syncInteractiveMessage($data),
            'templateMessage' => $this->syncTemplateMessage($data),
            'mediaMessage' => $this->syncMediaMessage($data),
            'waitForResponse' => $this->syncWaitForResponse($data),
            'delay' => $this->syncDelay($data),
            'typingIndicator' => $this->syncTypingIndicator($data),
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
        $keyword = trim((string) ($data['triggerKeyword'] ?? $data['keywords'] ?? ''));

        // Prefer React `welcomeMessage`, then `message`, then non-keyword `text`.
        $body = trim((string) ($data['welcomeMessage'] ?? ''));
        if ($body === '') {
            $body = trim((string) ($data['message'] ?? ''));
        }
        if ($body === '') {
            $text = trim((string) ($data['text'] ?? ''));
            if ($text !== '' && ($keyword === '' || strcasecmp($text, $keyword) !== 0)) {
                $body = $text;
            }
        }

        $data['welcomeMessage'] = $body;
        $data['text'] = $body;
        $data['message'] = $body;
        $data['triggerKeyword'] = $keyword;
        $data['keywords'] = $keyword;
        $data['messageType'] = (string) ($data['messageType'] ?? 'text');

        return $this->syncOfflineHoursFields($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncInteractiveMessage(array $data): array
    {
        $body = (string) ($data['bodyText'] ?? $data['text'] ?? $data['message'] ?? '');
        if ($body === '' && is_array($data['body'] ?? null)) {
            $body = (string) ($data['body']['text'] ?? '');
        }

        $interactiveType = strtolower((string) ($data['interactiveType'] ?? $data['interactive_type'] ?? $data['type'] ?? 'button'));
        $buttons = $this->normalizeButtons($data);

        $data['bodyText'] = $body;
        $data['text'] = $body;
        $data['message'] = $body;
        $data['interactiveType'] = $interactiveType;
        $data['interactive_type'] = $interactiveType;
        $data['buttons'] = $buttons;
        $data['options'] = array_column($buttons, 'title');

        if (! empty($data['sections']) && is_array($data['sections'])) {
            $data['listItems'] = $data['sections'];
        }

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

        return $this->syncOfflineHoursFields($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncOfflineHoursFields(array $data): array
    {
        if (array_key_exists('enableOfflineHours', $data)) {
            $data['enableOfflineHours'] = OfflineHoursEvaluator::truthy($data['enableOfflineHours']);
        }

        foreach (['timezone', 'onlineFrom', 'onlineUntil', 'offlineMessage'] as $offlineKey) {
            if (! array_key_exists($offlineKey, $data)) {
                continue;
            }

            if (is_string($data[$offlineKey])) {
                $data[$offlineKey] = trim($data[$offlineKey]);

                continue;
            }

            // Ant Design TimePicker may persist a moment/dayjs-like object or ISO string.
            if (is_object($data[$offlineKey]) && method_exists($data[$offlineKey], 'format')) {
                try {
                    $data[$offlineKey] = (string) $data[$offlineKey]->format('H:i');

                    continue;
                } catch (\Throwable) {
                    // fall through
                }
            }

            $data[$offlineKey] = is_scalar($data[$offlineKey]) ? trim((string) $data[$offlineKey]) : '';
        }

        // Normalize times to HH:mm when parseable.
        foreach (['onlineFrom', 'onlineUntil'] as $timeKey) {
            if (! isset($data[$timeKey]) || ! is_string($data[$timeKey]) || $data[$timeKey] === '') {
                continue;
            }

            if (preg_match('/^(\d{1,2}):(\d{2})/', $data[$timeKey], $m)) {
                $data[$timeKey] = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncMediaMessage(array $data): array
    {
        $caption = (string) ($data['text'] ?? $data['message'] ?? $data['caption'] ?? '');
        $url = trim((string) (
            $data['mediaUrl']
            ?? $data['media_url']
            ?? $data['fileUrl']
            ?? $data['file_url']
            ?? $data['url']
            ?? ''
        ));
        $path = trim((string) ($data['mediaPath'] ?? $data['media_path'] ?? ''));
        $type = strtolower(trim((string) ($data['mediaType'] ?? $data['media_type'] ?? 'image')));
        if ($type === '') {
            $type = 'image';
        }

        // Recover disk path from /storage/... URLs when the builder only saved a URL.
        if ($path === '' && $url !== '') {
            if (preg_match('#(?:^|/)storage/(.+)$#', $url, $matches) === 1) {
                $path = ltrim((string) $matches[1], '/');
            } elseif (! str_contains($url, '://') && str_starts_with($url, 'chatbot/')) {
                $path = ltrim($url, '/');
            }
        }

        $data['text'] = $caption;
        $data['message'] = $caption;
        $data['caption'] = $caption;
        $data['mediaUrl'] = $url;
        $data['media_url'] = $url;
        $data['fileUrl'] = $url;
        $data['file_url'] = $url;
        $data['mediaPath'] = $path !== '' ? $path : null;
        $data['media_path'] = $path !== '' ? $path : null;
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
        $seconds = null;

        if (isset($data['delaySeconds']) || isset($data['delay_seconds']) || isset($data['duration'])) {
            $seconds = (int) ($data['delaySeconds'] ?? $data['delay_seconds'] ?? $data['duration']);
        } elseif (
            isset($data['delayInHours'])
            || isset($data['delayInMinutes'])
            || isset($data['delayInSeconds'])
        ) {
            $seconds = ((int) ($data['delayInHours'] ?? 0)) * 3600
                + ((int) ($data['delayInMinutes'] ?? 0)) * 60
                + ((int) ($data['delayInSeconds'] ?? 0));
        }

        if ($seconds === null || $seconds < 1) {
            $seconds = 5;
        }

        // Allow up to 7 days for long delays configured in the builder.
        $seconds = max(1, min(604800, $seconds));

        $data['delay_seconds'] = $seconds;
        $data['delaySeconds'] = $seconds;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncTypingIndicator(array $data): array
    {
        $seconds = (int) ($data['duration'] ?? $data['durationSeconds'] ?? $data['delaySeconds'] ?? $data['delay_seconds'] ?? 3);

        if (! isset($data['duration']) && ! isset($data['durationSeconds']) && ! isset($data['delaySeconds']) && ! isset($data['delay_seconds'])) {
            $seconds = match ((string) ($data['typingSpeed'] ?? 'normal')) {
                'slow' => 2,
                'fast' => 1,
                'custom' => (int) round((float) ($data['customDuration'] ?? 3)),
                default => 3,
            };
        }

        $seconds = max(1, min(60, $seconds));

        $data['duration'] = $seconds;
        $data['durationSeconds'] = $seconds;
        $data['delaySeconds'] = $seconds;
        $data['delay_seconds'] = $seconds;
        $data['showTyping'] = $this->boolFlag($data['showTyping'] ?? true);
        $data['repeatTyping'] = $this->boolFlag($data['repeatTyping'] ?? false);

        if (array_key_exists('maxRepeats', $data) && $data['maxRepeats'] !== null && $data['maxRepeats'] !== '') {
            $data['maxRepeats'] = max(1, min(10, (int) $data['maxRepeats']));
        }

        return $data;
    }

    private function boolFlag(mixed $flag): bool
    {
        return $flag === true || $flag === 1 || $flag === '1' || $flag === 'true';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncCondition(array $data): array
    {
        $variable = (string) ($data['condition_variable'] ?? $data['variable'] ?? $data['field'] ?? '');
        $operator = (string) ($data['condition_operator'] ?? $data['operator'] ?? 'equals');
        $value = (string) ($data['condition_value'] ?? $data['value'] ?? '');

        if ($variable === '') {
            $variable = 'user_response';
        }

        $data['condition_variable'] = $variable;
        $data['variable'] = $variable;
        $data['field'] = $variable;
        $data['condition_operator'] = $operator;
        $data['operator'] = $operator;
        $data['condition_value'] = $value;
        $data['value'] = $value;

        if (isset($data['conditions']) && is_array($data['conditions'])) {
            $data['conditions'] = array_map(static function ($condition): array {
                if (! is_array($condition)) {
                    return [];
                }

                $field = (string) ($condition['field'] ?? $condition['variable'] ?? 'user_response');
                $type = (string) ($condition['type'] ?? '');
                $operator = (string) ($condition['operator'] ?? '');

                if ($operator === '' || in_array($type, ['exact', 'contains', 'starts_with', 'ends_with', 'regex'], true)) {
                    $operator = match ($type) {
                        'exact' => 'equals',
                        'contains' => 'contains',
                        'starts_with' => 'starts_with',
                        'ends_with' => 'ends_with',
                        'regex' => 'regex',
                        default => ($operator !== '' ? $operator : 'equals'),
                    };
                }

                $condition['field'] = $field !== '' ? $field : 'user_response';
                $condition['variable'] = $condition['field'];
                $condition['operator'] = $operator;
                $condition['value'] = (string) ($condition['value'] ?? '');

                return $condition;
            }, $data['conditions']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncHttpRequest(array $data): array
    {
        $data['method'] = strtoupper((string) ($data['method'] ?? 'GET'));
        $data['enabled'] = $this->boolFlag($data['enabled'] ?? true);

        $resultVariable = (string) (
            $data['resultVariable']
            ?? $data['responseVariable']
            ?? $data['result_variable']
            ?? 'http_response'
        );
        $data['resultVariable'] = $resultVariable !== '' ? $resultVariable : 'http_response';
        $data['responseVariable'] = $data['resultVariable'];

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

        $resultVariable = (string) (
            $data['resultVariable']
            ?? $data['returnVariable']
            ?? $data['result_variable']
            ?? 'function_result'
        );
        $data['resultVariable'] = $resultVariable;
        $data['returnVariable'] = $resultVariable;

        if (isset($data['parameters']) && is_array($data['parameters'])) {
            $assoc = [];
            $isList = array_is_list($data['parameters']);

            foreach ($data['parameters'] as $key => $value) {
                if ($isList && is_array($value)) {
                    $paramKey = (string) ($value['key'] ?? $value['name'] ?? '');
                    if ($paramKey === '') {
                        continue;
                    }
                    $assoc[$paramKey] = $value['value'] ?? null;
                    continue;
                }

                $assoc[(string) $key] = $value;
            }

            $data['parameters'] = $assoc;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncDateTimeCondition(array $data): array
    {
        $timezone = trim((string) ($data['timezone'] ?? config('app.timezone', 'Asia/Kolkata')));
        if ($timezone === '') {
            $timezone = (string) config('app.timezone', 'Asia/Kolkata');
        }
        $data['timezone'] = $timezone;

        $mode = (string) ($data['mode'] ?? $data['conditionType'] ?? $data['condition_type'] ?? 'business_hours');
        $data['mode'] = $mode;
        $data['conditionType'] = $mode;
        $data['condition_type'] = $mode;

        foreach (['start_time', 'end_time', 'startTime', 'endTime'] as $timeKey) {
            if (! array_key_exists($timeKey, $data)) {
                continue;
            }

            if (is_object($data[$timeKey]) && method_exists($data[$timeKey], 'format')) {
                try {
                    $data[$timeKey] = (string) $data[$timeKey]->format('H:i');
                } catch (\Throwable) {
                    $data[$timeKey] = '';
                }
            } elseif (is_scalar($data[$timeKey])) {
                $data[$timeKey] = trim((string) $data[$timeKey]);
            } else {
                $data[$timeKey] = '';
            }

            if (is_string($data[$timeKey]) && preg_match('/^(\d{1,2}):(\d{2})/', $data[$timeKey], $m)) {
                $data[$timeKey] = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
            }
        }

        if (isset($data['start_time']) || isset($data['startTime'])) {
            $start = (string) ($data['start_time'] ?? $data['startTime'] ?? '09:00');
            $data['start_time'] = $start;
            $data['startTime'] = $start;
        }

        if (isset($data['end_time']) || isset($data['endTime'])) {
            $end = (string) ($data['end_time'] ?? $data['endTime'] ?? '18:00');
            $data['end_time'] = $end;
            $data['endTime'] = $end;
        }

        $days = $data['enabled_days'] ?? $data['enabledDays'] ?? $data['selected_days'] ?? $data['selectedDays'] ?? null;
        if (is_array($days)) {
            $days = array_values(array_filter(array_map(
                static fn ($d) => strtolower(trim((string) $d)),
                $days,
            ), static fn ($d) => $d !== ''));
            $data['enabled_days'] = $days;
            $data['enabledDays'] = $days;
            $data['selected_days'] = $days;
            $data['selectedDays'] = $days;
        }

        if (isset($data['holidays']) && is_array($data['holidays'])) {
            $data['holidays'] = array_values(array_filter(array_map(
                static fn ($h) => trim((string) $h),
                $data['holidays'],
            ), static fn ($h) => $h !== ''));
        }

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
