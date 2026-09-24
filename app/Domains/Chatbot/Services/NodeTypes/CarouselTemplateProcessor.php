<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Enums\ChatbotFlowStateStatus;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use App\Models\Template;

class CarouselTemplateProcessor extends AbstractNodeProcessor
{
    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $data = $this->nodeData($node);
        $variables = $state->variables ?? [];

        $templateId = $data['templateId'] ?? $data['selectedTemplate']['id'] ?? null;
        $templateCode = (string) (
            $data['templateCode']
            ?? $data['selectedTemplate']['template_code']
            ?? $data['selectedTemplate']['code']
            ?? ''
        );

        if (($templateCode === '' || $templateCode === '0') && filled($templateId)) {
            $template = Template::query()->find($templateId);
            if ($template !== null) {
                $templateCode = (string) ($template->whatsappCode() ?: $template->code ?: $template->name);
            }
        }

        $cards = $data['templateCards']
            ?? $data['cards']
            ?? $data['items']
            ?? [];

        if (! is_array($cards)) {
            $cards = [];
        }

        // Legacy parity: send the approved WhatsApp carousel template (not a text list).
        if ($templateCode !== '' && $templateCode !== '0') {
            $params = $this->resolveTemplateSendParams($data, $conversation, $variables);
            $this->sendTemplate($conversation, $templateCode, $params);
        } else {
            // Fallback when template is missing — keep a readable list so the flow can continue.
            $headerText = $this->resolveText((string) ($data['headerText'] ?? $data['text'] ?? ''), $variables, $conversation);
            if ($headerText !== '') {
                $this->sendText($conversation, $headerText);
            }

            if ($cards !== []) {
                $lines = [];
                foreach ($cards as $i => $card) {
                    if (! is_array($card)) {
                        continue;
                    }
                    $title = (string) ($card['title'] ?? $card['body'] ?? $card['body_text'] ?? '');
                    if ($title === '') {
                        continue;
                    }
                    $lines[] = ($i + 1).'. '.$title;
                }
                if ($lines !== []) {
                    $this->sendText($conversation, implode("\n", $lines));
                }
            }
        }

        $state->mergeVariables([
            '_carousel_cards' => $cards,
            '_carousel_node_id' => (string) ($node['id'] ?? ''),
            '_wait_variable_name' => 'user_response',
        ]);
        $state->forceFill([
            'status' => ChatbotFlowStateStatus::Waiting,
            'current_node_id' => (string) ($node['id'] ?? ''),
        ])->save();

        return NodeProcessResult::WaitForResponse;
    }
}
