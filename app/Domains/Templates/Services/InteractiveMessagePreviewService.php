<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Models\InteractiveMessage;

class InteractiveMessagePreviewService
{
    /**
     * @return array{
     *     title: string,
     *     body: string,
     *     footer: string,
     *     header_type: string,
     *     header_text: string,
     *     header_image: string|null,
     *     buttons: list<array{text: string, type: string}>,
     *     button_mode: string,
     *     list_button_text: string,
     *     list_sections: list<array<string, mixed>>,
     *     interactive_type: string
     * }
     */
    public function forMessage(InteractiveMessage $message): array
    {
        $content = $message->normalizedContent();
        $header = $content['header'];
        $headerImage = null;
        $type = (string) $message->type;

        if (($header['type'] ?? 'none') === 'image' && filled($header['media_path'] ?? null)) {
            $headerImage = app(TemplateMediaService::class)->previewUrl((string) $header['media_path']);
        }

        $buttons = [];
        if ($type === 'button') {
            $buttons = collect($content['buttons'] ?? [])
                ->filter(fn ($button) => is_array($button) && filled($button['text'] ?? $button['title'] ?? null))
                ->map(fn (array $button): array => [
                    'text' => (string) ($button['text'] ?? $button['title'] ?? ''),
                    'type' => (string) ($button['type'] ?? 'quick_reply'),
                ])
                ->values()
                ->all();
        } elseif ($type === 'list') {
            $buttons = [[
                'text' => (string) ($content['list_button_text'] ?: 'View options'),
                'type' => 'list',
            ]];
        } elseif ($type === 'flow') {
            $buttons = [[
                'text' => (string) ($content['flow_cta'] ?: 'Open'),
                'type' => 'flow',
            ]];
        } elseif ($type === 'product') {
            $buttons = [[
                'text' => 'View product',
                'type' => 'product',
            ]];
        }

        $body = (string) ($content['body'] ?? '');
        if ($type === 'product' && $body === '') {
            $body = 'Product: '.((string) ($content['product_retailer_id'] ?? ''));
        }

        return [
            'title' => $message->name,
            'body' => $body,
            'footer' => (string) ($content['footer'] ?? ''),
            'header_type' => (string) ($header['type'] ?? 'none'),
            'header_text' => (string) ($header['text'] ?? ''),
            'header_image' => $headerImage,
            'buttons' => $buttons,
            'button_mode' => $type === 'list' ? 'list' : 'call_to_action',
            'list_button_text' => (string) ($content['list_button_text'] ?? ''),
            'list_sections' => array_values($content['list_sections'] ?? []),
            'interactive_type' => $type,
        ];
    }
}
