<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

class TemplateMessageProcessor extends AbstractNodeProcessor
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
            $templateCode = (string) ($data['templateId'] ?? $data['templateCode'] ?? '');
            $params = $data['templateParams'] ?? [];

            if ($templateCode !== '') {
                $this->sendTemplate($conversation, $templateCode, is_array($params) ? $params : []);
            }
        } else {
            $text = (string) ($data['text'] ?? '');

            if ($text !== '') {
                $resolved = $this->resolveText($text, $variables);
                $this->sendText($conversation, $resolved);
            }

            // Send quick replies as numbered options if present
            $quickReplies = $data['quickReplies'] ?? [];

            if (is_array($quickReplies) && $quickReplies !== []) {
                $lines = [];

                foreach ($quickReplies as $i => $reply) {
                    $title = is_string($reply) ? $reply : (string) ($reply['title'] ?? $reply['text'] ?? '');

                    if ($title !== '') {
                        $lines[] = ($i + 1).'. '.$title;
                    }
                }

                if ($lines !== []) {
                    $this->sendText($conversation, implode("\n", $lines));
                }

                // Store quick replies for response matching
                $state->mergeVariables([
                    '_quick_replies' => $quickReplies,
                    '_quick_reply_node_id' => (string) ($node['id'] ?? ''),
                ]);
            }
        }

        // Advance to next node
        $nextId = $this->defaultNextNodeId($node);

        if ($nextId !== null) {
            $state->forceFill(['current_node_id' => $nextId])->save();
        }

        return NodeProcessResult::Continue;
    }
}
