<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Support;

use App\Enums\ChatbotFlowStateStatus;
use App\Models\ChatbotFlowState;

/**
 * Re-arm Waiting on an option node without re-sending the WhatsApp message.
 * Used when the user taps a previous interactive/template/carousel message.
 */
final class OptionNodeWaitHydrator
{
    /**
     * @param  array<string, mixed>  $node
     */
    public static function supports(array $node): bool
    {
        $nodeClass = (string) ($node['class'] ?? $node['type'] ?? '');
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $interactiveType = strtolower((string) ($data['interactiveType'] ?? $data['interactive_type'] ?? $data['type'] ?? ''));

        if (in_array($nodeClass, ['interactiveMessage', 'carouselTemplate', 'whatsappFlowTemplate'], true)) {
            return true;
        }

        if (in_array($interactiveType, ['button', 'list', 'flow'], true)) {
            return true;
        }

        if ($nodeClass === 'templateMessage' || $nodeClass === 'welcomeMessage') {
            return TemplateMessageWaitHydrator::isWaitable($node);
        }

        if ($nodeClass === 'waitForResponse') {
            $enableQuickReplies = ($data['enableQuickReplies'] ?? false) === true
                || ($data['enableQuickReplies'] ?? false) === 1
                || ($data['enableQuickReplies'] ?? false) === '1'
                || ($data['enableQuickReplies'] ?? false) === 'true';

            return $enableQuickReplies && is_array($data['quickReplies'] ?? null) && $data['quickReplies'] !== [];
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public static function hydrate(ChatbotFlowState $state, array $node): void
    {
        $nodeClass = (string) ($node['class'] ?? $node['type'] ?? '');
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $nodeId = (string) ($node['id'] ?? '');

        if ($nodeClass === 'templateMessage' || $nodeClass === 'welcomeMessage') {
            TemplateMessageWaitHydrator::hydrateWaitingState($state, $node);

            return;
        }

        if ($nodeClass === 'carouselTemplate') {
            $cards = $data['templateCards'] ?? $data['cards'] ?? $data['items'] ?? [];
            if (! is_array($cards)) {
                $cards = [];
            }

            $state->mergeVariables([
                '_carousel_cards' => $cards,
                '_carousel_node_id' => $nodeId,
                '_wait_variable_name' => 'user_response',
            ]);
            $state->forceFill([
                'status' => ChatbotFlowStateStatus::Waiting,
                'current_node_id' => $nodeId,
            ])->save();

            return;
        }

        if ($nodeClass === 'waitForResponse') {
            $quickReplies = is_array($data['quickReplies'] ?? null) ? array_values($data['quickReplies']) : [];
            $variableName = trim((string) ($data['variableName'] ?? $data['variable'] ?? 'user_response'));
            if ($variableName === '') {
                $variableName = 'user_response';
            }

            $state->mergeVariables([
                '_quick_replies' => $quickReplies,
                '_wait_variable_name' => $variableName,
                '_wait_node_id' => $nodeId,
            ]);
            $state->forceFill([
                'status' => ChatbotFlowStateStatus::Waiting,
                'current_node_id' => $nodeId,
            ])->save();

            return;
        }

        // interactiveMessage / whatsappFlowTemplate / button|list shaped nodes
        $interactiveType = strtolower((string) ($data['interactiveType'] ?? $data['interactive_type'] ?? $data['type'] ?? 'button'));
        $state->mergeVariables([
            '_interactive_node_id' => $nodeId,
            '_interactive_options' => self::buildInteractiveOptions($data, $interactiveType),
            '_interactive_type' => $interactiveType,
            '_wait_variable_name' => 'user_response',
            '_whatsapp_flow_node_id' => $interactiveType === 'flow' ? $nodeId : ($state->variables['_whatsapp_flow_node_id'] ?? null),
        ]);
        $state->forceFill([
            'status' => ChatbotFlowStateStatus::Waiting,
            'current_node_id' => $nodeId,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{id: string, title: string}>
     */
    private static function buildInteractiveOptions(array $data, string $interactiveType): array
    {
        $options = [];

        if ($interactiveType === 'button' || isset($data['buttons'])) {
            foreach (array_values($data['buttons'] ?? $data['options'] ?? []) as $index => $button) {
                if (is_string($button)) {
                    $options[] = ['id' => "btn_{$index}", 'title' => $button];

                    continue;
                }
                if (! is_array($button)) {
                    continue;
                }
                $options[] = [
                    'id' => (string) ($button['id'] ?? "btn_{$index}"),
                    'title' => (string) ($button['title'] ?? $button['text'] ?? $button['label'] ?? ''),
                ];
            }

            if ($options !== []) {
                return $options;
            }
        }

        foreach ($data['sections'] ?? $data['list_sections'] ?? $data['listItems'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }
            foreach (array_values($section['rows'] ?? $section['items'] ?? []) as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $options[] = [
                    'id' => (string) ($row['id'] ?? "list_{$index}"),
                    'title' => (string) ($row['title'] ?? $row['text'] ?? $row['label'] ?? ''),
                ];
            }
        }

        return $options;
    }
}
