<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

class MediaMessageProcessor extends AbstractNodeProcessor
{
    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $data = $this->nodeData($node);
        $caption = (string) ($data['caption'] ?? '');
        $variables = $state->variables ?? [];

        if ($caption !== '') {
            $resolved = $this->resolveText($caption, $variables);
            $this->sendText($conversation, $resolved);
        }

        // Media URL is sent as text with the URL — actual media upload handled at webhook layer
        $mediaUrl = (string) ($data['mediaUrl'] ?? $data['url'] ?? '');

        if ($mediaUrl !== '') {
            $this->sendText($conversation, $mediaUrl);
        }

        $nextId = $this->defaultNextNodeId($node);

        if ($nextId !== null) {
            $state->forceFill(['current_node_id' => $nextId])->save();
        }

        return NodeProcessResult::Continue;
    }
}
