<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Support\OfflineHoursEvaluator;
use App\Enums\ChatbotFlowStateStatus;
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
            // Offline hours: send text offlineMessage instead of the online template (legacy parity).
            if (OfflineHoursEvaluator::shouldSendOfflineMessage($data)) {
                $offline = OfflineHoursEvaluator::resolveSessionText($data, '');
                if ($offline !== '') {
                    $this->sendText($conversation, $this->resolveText($offline, $variables, $conversation));
                }
            } else {
                $templateCode = (string) ($data['templateId'] ?? $data['templateCode'] ?? $data['template_name'] ?? '');

                if ($templateCode === '' && is_array($data['selectedTemplate'] ?? null)) {
                    $templateCode = (string) ($data['selectedTemplate']['code']
                        ?? $data['selectedTemplate']['template_code']
                        ?? $data['selectedTemplate']['id']
                        ?? '');
                }

                if ($templateCode !== '') {
                    $this->sendTemplate($conversation, $templateCode);
                }
            }
        } else {
            $text = OfflineHoursEvaluator::resolveSessionText($data, $this->resolveWelcomeBody($data));

            if ($text !== '') {
                $this->sendText($conversation, $this->resolveText($text, $variables, $conversation));
            }
        }

        $quickReplies = $this->resolveQuickReplies($data);
        if ($quickReplies !== [] || $this->hasQuickReplyBranches($node)) {
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
     * @param  array<string, mixed>  $data
     */
    private function resolveWelcomeBody(array $data): string
    {
        $keyword = trim((string) ($data['triggerKeyword'] ?? $data['keywords'] ?? ''));

        foreach (['welcomeMessage', 'message'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $text = trim((string) ($data['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        // React builder used to mirror triggerKeyword into `text` — never send that as the body.
        if ($keyword !== '' && strcasecmp($text, $keyword) === 0) {
            return '';
        }

        // Comma-separated keyword lists are also not welcome copy.
        if ($keyword !== '' && $this->textLooksLikeKeywordList($text)) {
            return '';
        }

        return $text;
    }

    private function textLooksLikeKeywordList(string $text): bool
    {
        if (str_contains($text, "\n") || mb_strlen($text) > 80) {
            return false;
        }

        $parts = array_filter(array_map('trim', explode(',', $text)));

        return count($parts) > 1 && count($parts) === count(explode(',', $text));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, mixed>
     */
    private function resolveQuickReplies(array $data): array
    {
        $quickReplies = $data['quickReplies'] ?? [];
        if (is_array($quickReplies) && $quickReplies !== []) {
            return array_values($quickReplies);
        }

        $selected = is_array($data['selectedTemplate'] ?? null) ? $data['selectedTemplate'] : [];
        $fromTemplate = [];
        for ($i = 1; $i <= 10; $i++) {
            $text = trim((string) ($selected['auto_reply_text_'.$i] ?? $data['auto_reply_text_'.$i] ?? ''));
            if ($text !== '') {
                $fromTemplate[] = ['id' => $i, 'text' => $text];
            }
        }

        return $fromTemplate;
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
