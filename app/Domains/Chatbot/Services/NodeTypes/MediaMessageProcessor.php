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
        $mediaUrl = trim((string) (
            $data['mediaUrl']
            ?? $data['media_url']
            ?? $data['fileUrl']
            ?? $data['file_url']
            ?? $data['url']
            ?? ''
        ));
        $mediaPath = trim((string) ($data['mediaPath'] ?? $data['media_path'] ?? ''));
        $mediaType = strtolower((string) ($data['mediaType'] ?? $data['media_type'] ?? 'image'));

        if ($mediaPath === '' && $mediaUrl !== '' && preg_match('#(?:^|/)storage/(.+)$#', $mediaUrl, $matches) === 1) {
            $mediaPath = ltrim((string) $matches[1], '/');
        }

        if ($mediaUrl !== '' || $mediaPath !== '') {
            $this->outboundService->sendMediaFromUrl(
                conversation: $conversation,
                mediaUrl: $mediaUrl !== '' ? $mediaUrl : $mediaPath,
                mediaType: $mediaType !== '' ? $mediaType : 'image',
                caption: $caption !== '' ? $caption : null,
                fileName: isset($data['fileName']) ? (string) $data['fileName'] : null,
                enforceWindow: false,
                mediaPath: $mediaPath !== '' ? $mediaPath : null,
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
