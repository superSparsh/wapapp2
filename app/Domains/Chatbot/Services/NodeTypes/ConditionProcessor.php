<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

class ConditionProcessor extends AbstractNodeProcessor
{
    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $data = $this->nodeData($node);
        $variables = $state->variables ?? [];

        $nextId = $this->evaluateCondition($data, $node, $variables);

        if ($nextId !== null) {
            $state->forceFill(['current_node_id' => $nextId])->save();
        }

        return $nextId !== null ? NodeProcessResult::Continue : NodeProcessResult::Completed;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $variables
     */
    protected function evaluateCondition(array $data, array $node, array $variables): ?string
    {
        $field = (string) ($data['field'] ?? $data['variable'] ?? '');
        $operator = (string) ($data['operator'] ?? 'equals');
        $value = (string) ($data['value'] ?? '');

        // Resolve the field value from variables
        $fieldValue = (string) ($variables[$field] ?? '');

        $matched = $this->compare($fieldValue, $operator, $value);

        // Matched branch: output_yes / output_true / output_1
        // Unmatched branch: output_no / output_false / output_2
        if ($matched) {
            return $this->nextNodeIdFromHandle($node, 'output_yes')
                ?? $this->nextNodeIdFromHandle($node, 'output_true')
                ?? $this->nextNodeIdFromHandle($node, 'output_1')
                ?? $this->defaultNextNodeId($node);
        }

        return $this->nextNodeIdFromHandle($node, 'output_no')
            ?? $this->nextNodeIdFromHandle($node, 'output_false')
            ?? $this->nextNodeIdFromHandle($node, 'output_2')
            ?? $this->defaultNextNodeId($node);
    }

    protected function compare(string $fieldValue, string $operator, string $value): bool
    {
        $fieldLower = mb_strtolower($fieldValue);
        $valueLower = mb_strtolower($value);

        return match ($operator) {
            'equals', 'is', '=' => $fieldLower === $valueLower,
            'not_equals', '!=', 'is_not' => $fieldLower !== $valueLower,
            'contains' => $value !== '' && str_contains($fieldLower, $valueLower),
            'not_contains' => $value === '' || ! str_contains($fieldLower, $valueLower),
            'starts_with' => str_starts_with($fieldLower, $valueLower),
            'ends_with' => str_ends_with($fieldLower, $valueLower),
            'greater_than', '>' => is_numeric($fieldValue) && is_numeric($value) && (float) $fieldValue > (float) $value,
            'less_than', '<' => is_numeric($fieldValue) && is_numeric($value) && (float) $fieldValue < (float) $value,
            'greater_equal', '>=' => is_numeric($fieldValue) && is_numeric($value) && (float) $fieldValue >= (float) $value,
            'less_equal', '<=' => is_numeric($fieldValue) && is_numeric($value) && (float) $fieldValue <= (float) $value,
            'is_empty', 'empty' => $fieldValue === '',
            'is_not_empty', 'not_empty' => $fieldValue !== '',
            'regex' => $value !== '' && (bool) preg_match('/'.$value.'/', $fieldValue),
            default => false,
        };
    }
}
