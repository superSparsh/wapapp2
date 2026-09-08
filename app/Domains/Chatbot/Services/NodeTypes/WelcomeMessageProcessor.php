<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

class WelcomeMessageProcessor extends AbstractNodeProcessor
{
    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $data = $this->nodeData($node);
        $messageType = (string) ($data['messageType'] ?? 'text');
        $variables = $state->variables ?? [];

        if ($messageType === 'template') {
            $templateCode = (string) ($data['templateId'] ?? '');

            if ($templateCode !== '') {
                $this->sendTemplate($conversation, $templateCode);
            }
        } else {
            $text = (string) ($data['text'] ?? '');

            if ($text !== '') {
                $resolved = $this->resolveText($text, $variables);
                $this->sendText($conversation, $resolved);
            }
        }

        // Advance state to next node
        $nextId = $this->defaultNextNodeId($node);

        if ($nextId !== null) {
            $state->forceFill(['current_node_id' => $nextId])->save();
        }

        return NodeProcessResult::Continue;
    }
}
