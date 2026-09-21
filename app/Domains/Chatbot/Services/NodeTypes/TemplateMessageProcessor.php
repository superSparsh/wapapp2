<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Support\OfflineHoursEvaluator;
use App\Enums\ChatbotFlowStateStatus;
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

        if (OfflineHoursEvaluator::shouldSendOfflineMessage($data)) {
            $offline = OfflineHoursEvaluator::resolveSessionText($data, '');
            if ($offline !== '') {
                $this->sendText($conversation, $this->resolveText($offline, $variables));
            }
        } elseif ($messageType === 'template') {
            $templateCode = (string) ($data['templateId'] ?? $data['templateCode'] ?? '');
            $params = $data['templateParams'] ?? [];

            if ($templateCode !== '') {
                $this->sendTemplate($conversation, $templateCode, is_array($params) ? $params : []);
            }
        } else {
            $text = (string) ($data['text'] ?? $data['message'] ?? $data['welcomeMessage'] ?? '');

            if ($text !== '') {
                $this->sendText($conversation, $this->resolveText($text, $variables));
            }
        }

        $quickReplies = $data['quickReplies'] ?? [];
        if (! is_array($quickReplies)) {
            $quickReplies = [];
        }

        if ($quickReplies !== [] || $this->hasQuickReplyBranches($node)) {
            if ($quickReplies !== [] && $messageType !== 'template') {
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
            }

            $state->mergeVariables([
                '_quick_replies' => $quickReplies,
                '_quick_reply_node_id' => (string) ($node['id'] ?? ''),
                '_wait_variable_name' => 'user_response',
            ]);
            $state->forceFill([
                'status' => ChatbotFlowStateStatus::Waiting,
                'current_node_id' => (string) ($node['id'] ?? ''),
            ])->save();

            return NodeProcessResult::WaitForResponse;
        }

        $nextId = $this->defaultNextNodeId($node);

        if ($nextId !== null) {
            $state->forceFill(['current_node_id' => $nextId])->save();
        }

        return NodeProcessResult::Continue;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function hasQuickReplyBranches(array $node): bool
    {
        foreach (array_keys($node['outputs'] ?? []) as $handle) {
            $handle = (string) $handle;
            if (str_starts_with($handle, 'reply-') || str_starts_with($handle, 'reply_')) {
                $connections = $node['outputs'][$handle]['connections'] ?? [];
                if ($connections !== []) {
                    return true;
                }
            }
        }

        return false;
    }
}
