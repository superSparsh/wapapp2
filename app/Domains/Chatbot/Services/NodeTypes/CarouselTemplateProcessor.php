<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

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

        $headerText = $this->resolveText((string) ($data['headerText'] ?? $data['text'] ?? ''), $variables);
        $cards = $data['cards'] ?? $data['items'] ?? [];

        if ($headerText !== '') {
            $this->sendText($conversation, $headerText);
        }

        // Format carousel cards as numbered list
        if (is_array($cards) && $cards !== []) {
            $lines = [];

            foreach ($cards as $i => $card) {
                $title = (string) ($card['title'] ?? '');
                $subtitle = (string) ($card['subtitle'] ?? $card['description'] ?? '');

                $entry = ($i + 1).'. '.$title;

                if ($subtitle !== '') {
                    $entry .= ' - '.$subtitle;
                }

                $lines[] = $entry;
            }

            $this->sendText($conversation, implode("\n", $lines));
        }

        // Store card options for response matching
        $state->mergeVariables([
            '_carousel_cards' => $cards,
            '_carousel_node_id' => (string) ($node['id'] ?? ''),
        ]);

        return NodeProcessResult::WaitForResponse;
    }
}
