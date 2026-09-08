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
            $field = (string) ($condition['field'] ?? $condition['variable'] ?? '');
            $operator = (string) ($condition['operator'] ?? 'equals');
            $value = (string) ($condition['value'] ?? '');
            $fieldValue = (string) ($variables[$field] ?? '');

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
}
