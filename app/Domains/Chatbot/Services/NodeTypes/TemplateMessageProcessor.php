<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Support\OfflineHoursEvaluator;
use App\Domains\Chatbot\Support\TemplateMessageWaitHydrator;
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
                $this->sendText($conversation, $this->resolveText($offline, $variables, $conversation));
            }

            // Outside hours: only the offline notice - do not continue to next nodes.
            return NodeProcessResult::Completed;
        }

        if ($messageType === 'template') {
            $templateCode = $this->resolveTemplateSendCode($data);

            if ($templateCode !== '') {
                $params = $this->resolveTemplateSendParams($data, $conversation, $variables);
                $this->sendTemplate($conversation, $templateCode, $params);
            }
        } else {
            $text = $this->resolveCustomBody($data);

            if ($text !== '') {
                $this->sendText($conversation, $this->resolveText($text, $variables, $conversation));
            }
        }

        $quickReplies = TemplateMessageWaitHydrator::resolveQuickReplies($data);

        if (TemplateMessageWaitHydrator::isWaitable($node)) {
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

            TemplateMessageWaitHydrator::hydrateWaitingState($state, $node);

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
    private function resolveCustomBody(array $data): string
    {
        $keyword = trim((string) ($data['triggerKeyword'] ?? $data['keywords'] ?? ''));

        foreach (['customText', 'message', 'welcomeMessage'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                if ($keyword !== '' && strcasecmp($value, $keyword) === 0) {
                    continue;
                }

                return $value;
            }
        }

        $text = trim((string) ($data['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        // Builder used to put triggerKeyword into text - never send that as the body.
        if ($keyword !== '' && strcasecmp($text, $keyword) === 0) {
            return '';
        }

        return $text;
    }
}
