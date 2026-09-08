<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Template;
use App\Repositories\Interfaces\TemplateRepositoryInterface;

class TemplatePreviewService
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forCode(string $code): array
    {
        $template = $this->templateRepository->findByCode($code);

        if (! $template instanceof Template) {
            return [
                'title' => $code,
                'body' => 'Template preview unavailable.',
                'footer' => '',
                'header_type' => 'none',
                'header_text' => '',
                'header_video' => null,
                'header_document' => null,
                'body_samples' => [],
                'buttons' => [],
                'button_mode' => 'call_to_action',
                'is_opt_out' => false,
                'header_image' => null,
                'rejection_reason' => null,
            ];
        }

        return $this->forTemplate($template);
    }

    /**
     * @return array<string, mixed>
     */
    public function forTemplate(Template $template): array
    {
        $payload = $template->wizardPayload();
        $buttonMode = (string) ($payload['button_mode'] ?? 'call_to_action');
        $isOptOut = (bool) ($payload['is_opt_out'] ?? false);

        $buttons = collect($payload['buttons'] ?? [])
            ->filter(fn ($button) => is_array($button) && filled($button['text'] ?? null))
            ->map(fn (array $button): array => [
                'text' => (string) $button['text'],
                'type' => (string) ($button['type'] ?? 'url'),
                'url' => (string) ($button['url'] ?? ''),
                'flow_id' => (string) ($button['flow_id'] ?? ''),
            ])
            ->values()
            ->all();

        $footerText = trim((string) ($payload['footer']['text'] ?? ''));
        if ($isOptOut && $footerText === '') {
            $footerText = 'Not interested? Tap Stop promotions';
        }

        $headerType = (string) ($payload['header']['type'] ?? 'none');
        $mediaPath = $payload['header']['media_path'] ?? null;
        $mediaUrl = $payload['header']['media_url'] ?? null;

        $auth = $payload['auth'] ?? [];
        if (($auth['enabled'] ?? false) || $template->category === 'AUTHENTICATION') {
            $buttonMode = 'authentication';
        }

        $lto = $payload['lto'] ?? [];
        if (($lto['enabled'] ?? false) || $template->category === 'LIMITED_TIME_OFFER') {
            $buttonMode = 'lto';
        }

        $carousel = $payload['carousel'] ?? [];

        return [
            'title' => $template->name,
            'body' => (string) ($template->body_preview ?: $payload['body']['text'] ?? $template->name),
            'footer' => $footerText,
            'header_type' => $headerType,
            'header_text' => (string) ($payload['header']['text'] ?? ''),
            'header_video' => $headerType === 'video' ? ($mediaUrl ?: $mediaPath) : null,
            'header_document' => $headerType === 'document' ? ($mediaUrl ?: $mediaPath) : null,
            'header_image' => $headerType === 'image' ? ($mediaUrl ?: $mediaPath) : null,
            'body_samples' => array_values($payload['body']['samples'] ?? []),
            'buttons' => $buttons,
            'button_mode' => $buttonMode,
            'is_opt_out' => $isOptOut,
            'rejection_reason' => $template->rejection_reason,
            'carousel_cards' => $carousel['cards'] ?? [],
            'lto' => $lto,
            'auth' => $auth,
        ];
    }
}
