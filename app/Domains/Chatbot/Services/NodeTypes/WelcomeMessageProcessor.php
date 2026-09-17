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
            $templateCode = (string) ($data['templateId'] ?? $data['templateCode'] ?? $data['template_name'] ?? '');

            if ($templateCode !== '') {
                $this->sendTemplate($conversation, $templateCode);
            }
        } else {
            $text = $this->resolveWelcomeBody($data);

            if ($text !== '') {
                $this->sendText($conversation, $this->resolveText($text, $variables));
            }
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

        // React builder mirrors triggerKeyword into `text` — never send that as the body.
        if ($keyword !== '' && strcasecmp($text, $keyword) === 0) {
            return '';
        }

        return $text;
    }
}
