<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Enums\ChatbotFlowStateStatus;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

class WaitForResponseProcessor extends AbstractNodeProcessor
{
    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $data = $this->nodeData($node);
        $variableName = (string) ($data['variableName'] ?? $data['variable'] ?? 'user_response');

        // Mark state as waiting and store which variable to capture the response into
        $state->forceFill([
            'status' => ChatbotFlowStateStatus::Waiting,
            'current_node_id' => (string) ($node['id'] ?? ''),
        ])->save();

        $state->mergeVariables([
            '_wait_variable_name' => $variableName,
            '_wait_node_id' => (string) ($node['id'] ?? ''),
        ]);

        return NodeProcessResult::WaitForResponse;
    }
}
