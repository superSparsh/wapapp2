<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

class EnhancedConditionProcessor extends ConditionProcessor
{
    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $data = $this->nodeData($node);
        $variables = $state->variables ?? [];

        // Enhanced conditions support multiple condition groups with AND/OR logic
        $conditions = $data['conditions'] ?? [];
        $logicOperator = (string) ($data['logicOperator'] ?? 'AND');

        if (! is_array($conditions) || $conditions === []) {
            // Fall back to single-condition evaluation
            $nextId = $this->evaluateCondition($data, $node, $variables);

            if ($nextId !== null) {
                $state->forceFill(['current_node_id' => $nextId])->save();
            }

            return $nextId !== null ? NodeProcessResult::Continue : NodeProcessResult::Completed;
        }

        $matched = $this->evaluateMultipleConditions($conditions, $logicOperator, $variables);

        $nextId = $matched
            ? ($this->nextNodeIdFromHandle($node, 'output_yes') ?? $this->nextNodeIdFromHandle($node, 'output_true') ?? $this->defaultNextNodeId($node))
            : ($this->nextNodeIdFromHandle($node, 'output_no') ?? $this->nextNodeIdFromHandle($node, 'output_false') ?? $this->defaultNextNodeId($node));

        if ($nextId !== null) {
            $state->forceFill(['current_node_id' => $nextId])->save();
        }

        return $nextId !== null ? NodeProcessResult::Continue : NodeProcessResult::Completed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $conditions
     * @param  array<string, mixed>  $variables
     */
    private function evaluateMultipleConditions(array $conditions, string $logicOperator, array $variables): bool
    {
        $isAnd = strtoupper($logicOperator) === 'AND';

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
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

            $value = (string) ($condition['value'] ?? '');
            $fieldValue = $this->resolveFieldValue($field, $variables);

            $result = $this->compare($fieldValue, $operator, $value);

            if ($isAnd && ! $result) {
                return false;
            }

            if (! $isAnd && $result) {
                return true;
            }
        }

        return $isAnd;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function resolveFieldValue(string $field, array $variables): string
    {
        if ($field !== '' && array_key_exists($field, $variables)) {
            return (string) $variables[$field];
        }

        // Common aliases for reply-based conditions.
        foreach (['user_response', '_last_reply', 'message_text', 'last_reply'] as $alias) {
            if (array_key_exists($alias, $variables) && (string) $variables[$alias] !== '') {
                return (string) $variables[$alias];
            }
        }

        return '';
    }
}
