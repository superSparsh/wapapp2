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
        $variables = $state->variables ?? [];
        $caption = $this->resolveText((string) ($data['caption'] ?? $data['text'] ?? $data['message'] ?? ''), $variables, $conversation);
        $mediaUrl = trim((string) ($data['mediaUrl'] ?? $data['media_url'] ?? $data['url'] ?? ''));
        $mediaType = strtolower((string) ($data['mediaType'] ?? $data['media_type'] ?? 'image'));

        if ($mediaUrl !== '') {
            $this->outboundService->sendMediaFromUrl(
                conversation: $conversation,
                mediaUrl: $mediaUrl,
                mediaType: $mediaType,
                caption: $caption !== '' ? $caption : null,
                fileName: isset($data['fileName']) ? (string) $data['fileName'] : null,
                enforceWindow: false,
            );
        } elseif ($caption !== '') {
            $this->sendText($conversation, $caption);
        }

        $nextId = $this->defaultNextNodeId($node);

        if ($nextId !== null) {
            $state->forceFill(['current_node_id' => $nextId])->save();
        }

        return NodeProcessResult::Continue;
    }
}
