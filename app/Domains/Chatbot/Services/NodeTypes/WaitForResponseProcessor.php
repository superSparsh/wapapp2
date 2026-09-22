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
        if ($variableName === '') {
            $variableName = 'user_response';
        }

        $variables = $state->variables ?? [];
        // Free-text wait should not inherit prior interactive/QR match requirements.
        unset($variables['_interactive_options'], $variables['_quick_replies']);

        $enableQuickReplies = $this->flagEnabled($data['enableQuickReplies'] ?? false);
        $quickReplies = is_array($data['quickReplies'] ?? null) ? $data['quickReplies'] : [];

        if ($enableQuickReplies && $quickReplies !== []) {
            $prompt = trim((string) ($data['quickReplyMessage'] ?? $data['message'] ?? ''));
            if ($prompt !== '') {
                $this->sendText(
                    $conversation,
                    $this->resolveText($prompt, $variables, $conversation),
                );
            }

            $variables['_quick_replies'] = array_values(array_filter(array_map(
                static function ($reply): ?array {
                    if (! is_array($reply)) {
                        return null;
                    }

                    $text = trim((string) ($reply['text'] ?? $reply['title'] ?? ''));
                    if ($text === '') {
                        return null;
                    }

                    return [
                        'text' => $text,
                        'id' => (string) ($reply['id'] ?? $reply['value'] ?? $text),
                    ];
                },
                $quickReplies,
            )));
        }

        $variables['_wait_variable_name'] = $variableName;
        $variables['_wait_node_id'] = (string) ($node['id'] ?? '');

        $state->forceFill([
            'status' => ChatbotFlowStateStatus::Waiting,
            'current_node_id' => (string) ($node['id'] ?? ''),
            'variables' => $variables,
        ])->save();

        return NodeProcessResult::WaitForResponse;
    }

    private function flagEnabled(mixed $flag): bool
    {
        return $flag === true || $flag === 1 || $flag === '1' || $flag === 'true';
    }
}
