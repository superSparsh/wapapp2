<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

class JumpToStepProcessor extends AbstractNodeProcessor
{
    private const MAX_JUMPS_PER_FLOW = 10;

    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $data = $this->nodeData($node);

        // Jump target can be specified via targetNodeId, targetNode, target_node, stepId, targetStep
        $targetNodeId = (string) (
            $data['targetNodeId']
            ?? $data['target_node']
            ?? $data['targetNode']
            ?? $data['targetStep']
            ?? $data['stepId']
            ?? ''
        );

        if ($targetNodeId === '') {
            // Try to resolve from outputs or edge connections
            $targetNodeId = $this->defaultNextNodeId($node) ?? '';
        }

        if ($targetNodeId === '' || ! isset($nodeMap[$targetNodeId])) {
            return NodeProcessResult::Error;
        }

        $variables = $state->variables ?? [];
        $jumpKey = '_jump_count_' . ($node['id'] ?? 'jump');
        $totalJumpCount = (int) ($variables['_total_jumps'] ?? 0);
        $nodeJumpCount = (int) ($variables[$jumpKey] ?? 0);

        // Loop safety guard
        if ($totalJumpCount >= self::MAX_JUMPS_PER_FLOW || $nodeJumpCount >= 5) {
            return NodeProcessResult::Completed;
        }

        $variables[$jumpKey] = $nodeJumpCount + 1;
        $variables['_total_jumps'] = $totalJumpCount + 1;
        $variables['_jumped_from'] = $node['id'] ?? null;

        // If jump message is configured, optionally send it
        if (! empty($data['showMessage']) && ! empty($data['jumpMessage'])) {
            $messageText = $this->resolveText((string) $data['jumpMessage'], $variables);
            $this->sendText($conversation, $messageText);
        }

        $state->forceFill([
            'current_node_id' => $targetNodeId,
            'variables' => $variables,
        ])->save();

        return NodeProcessResult::Continue;
    }
}
