<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Models\InteractiveMessage;

class InteractiveMessagePreviewService
{
    /**
     * @return array{title: string, body: string, footer: string, header_type: string, header_text: string, header_image: string|null, buttons: list<array{text: string, type: string}>}
     */
    public function forMessage(InteractiveMessage $message): array
    {
        $content = $message->normalizedContent();
        $header = $content['header'];
        $headerImage = null;

        if (($header['type'] ?? 'none') === 'image' && filled($header['media_path'] ?? null)) {
            $headerImage = asset('storage/'.$header['media_path']);
        }

        $buttons = collect($content['buttons'] ?? [])
            ->filter(fn ($button) => is_array($button) && filled($button['text'] ?? null))
            ->map(fn (array $button): array => [
                'text' => (string) $button['text'],
                'type' => (string) ($button['type'] ?? 'quick_reply'),
            ])
            ->values()
            ->all();

        return [
            'title' => $message->name,
            'body' => (string) ($content['body'] ?? ''),
            'footer' => (string) ($content['footer'] ?? ''),
            'header_type' => (string) ($header['type'] ?? 'none'),
            'header_text' => (string) ($header['text'] ?? ''),
            'header_image' => $headerImage,
            'buttons' => $buttons,
        ];
    }
}
