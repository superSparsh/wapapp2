<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Models\AiBot;
use App\Models\ChatbotFlow;
use App\Models\InteractiveMessage;
use App\Models\Template;

class ChatbotBuilderDataService
{
    /**
     * @return array{templates: array<int, array<string, mixed>>, interactiveMessages: array<int, array<string, mixed>>, automationBot: array<string, mixed>, aiBots: array<int, array<string, mixed>>}
     */
    public function payload(ChatbotFlow $flow): array
    {
        return [
            'templates' => $this->legacyTemplates(),
            'interactiveMessages' => $this->legacyInteractiveMessages(),
            'automationBot' => $this->legacyAutomationBot($flow),
            'aiBots' => $this->aiBots(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function legacyTemplates(): array
    {
        return Template::query()
            ->where('status', TemplateStatus::Approved)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Template $template): array => $this->mapTemplate($template))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTemplate(Template $template): array
    {
        $payload = $template->wizardPayload();
        $body = (string) ($payload['body']['text'] ?? $template->body_preview ?? '');
        $buttons = array_values($payload['buttons'] ?? []);
        $carousel = $payload['carousel'] ?? [];
        $isCarousel = ! empty($carousel['enabled']) && ! empty($carousel['cards']);

        $buttonType = 0;
        $buttonTextFlow = null;

        foreach ($buttons as $button) {
            $type = strtolower((string) ($button['type'] ?? $button['button_type'] ?? ''));

            if (in_array($type, ['quick_reply', 'quick-reply', 'quickreply'], true)) {
                $buttonType = 2;
            }

            if (in_array($type, ['flow', 'whatsapp_flow'], true)) {
                $buttonTextFlow = (string) ($button['text'] ?? $button['title'] ?? '');
                $buttonType = 4;
            }
        }

        if ($buttonType === 0 && $buttons !== []) {
            $buttonType = 1;
        }

        $mapped = [
            'id' => $template->id,
            'template_name' => $template->name,
            'template_code' => $template->code,
            'category' => $template->category,
            'language' => $template->language,
            'template_type' => $payload['meta']['template_type'] ?? 'regular',
            'actual_body' => $body,
            'body' => $body,
            'button_type' => $buttonType,
            'is_carousel_template' => $isCarousel ? 1 : 0,
            'button_text_flow' => $buttonTextFlow,
            'status' => 'Approved',
        ];

        $quickReplyIndex = 1;

        foreach ($buttons as $button) {
            $type = strtolower((string) ($button['type'] ?? $button['button_type'] ?? ''));

            if (! in_array($type, ['quick_reply', 'quick-reply', 'quickreply'], true)) {
                continue;
            }

            if ($quickReplyIndex > 10) {
                break;
            }

            $mapped['auto_reply_text_'.$quickReplyIndex] = (string) ($button['text'] ?? $button['title'] ?? '');
            $quickReplyIndex++;
        }

        return $mapped;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function legacyInteractiveMessages(): array
    {
        return InteractiveMessage::query()
            ->orderByDesc('id')
            ->get()
            ->map(function (InteractiveMessage $message): array {
                $content = $message->normalizedContent();

                return [
                    'id' => $message->id,
                    'name' => $message->name,
                    'type' => $message->type,
                    'content' => $content,
                    'body' => $content['body'],
                    'footer' => $content['footer'],
                    'buttons' => $content['buttons'],
                    'header' => $content['header'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyAutomationBot(ChatbotFlow $flow): array
    {
        return [
            'id' => $flow->id,
            'uuid' => $flow->uuid,
            'name' => $flow->name,
            'status' => $flow->status->value,
            'exported_data' => $flow->exported_data,
            'guided_tour' => 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function aiBots(): array
    {
        return AiBot::query()
            ->withCount('businessInfoEntries')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (AiBot $bot): array => [
                'id' => $bot->id,
                'name' => $bot->name,
                'provider' => $bot->provider->value ?? (string) $bot->provider,
                'chat_model' => $bot->chat_model,
                'is_default' => (bool) $bot->is_default,
                'status' => $bot->status,
                'business_info_count' => $bot->business_info_entries_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{cards: array<int, array<string, mixed>>}
     */
    public function templateCards(Template $template): array
    {
        $payload = $template->wizardPayload();
        $cards = array_values($payload['carousel']['cards'] ?? []);

        return [
            'cards' => collect($cards)->map(function (array $card, int $index): array {
                $buttons = array_values($card['buttons'] ?? []);

                return [
                    'id' => $card['id'] ?? ('card_'.$index),
                    'title' => (string) ($card['title'] ?? $card['header'] ?? ''),
                    'body' => (string) ($card['body'] ?? ''),
                    'image_url' => (string) ($card['image_url'] ?? $card['media_url'] ?? ''),
                    'buttons' => collect($buttons)->map(function (array $button, int $buttonIndex): array {
                        return [
                            'id' => (string) ($button['id'] ?? 'btn_'.$buttonIndex),
                            'title' => (string) ($button['text'] ?? $button['title'] ?? ''),
                            'type' => (string) ($button['type'] ?? 'quick_reply'),
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }
}
