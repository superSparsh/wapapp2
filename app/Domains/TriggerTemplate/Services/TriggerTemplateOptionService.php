<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Services;

use App\Domains\Templates\Services\TemplatePreviewService;
use App\Domains\Templates\Services\TemplateRegistryService;
use App\Models\MailList;
use Illuminate\Support\Collection;

class TriggerTemplateOptionService
{
    public function __construct(
        private readonly TemplateRegistryService $registry,
        private readonly TemplatePreviewService $previewService,
    ) {}

    /**
     * Approved templates without body/header variables (legacy UI warning parity).
     *
     * @return array<int, array{
     *     code: string,
     *     name: string,
     *     language: string,
     *     category: string,
     *     preview: array<string, mixed>
     * }>
     */
    public function templates(): array
    {
        return collect($this->registry->options())
            ->filter(function (array $option): bool {
                $code = (string) ($option['code'] ?? '');
                if ($code === '') {
                    return false;
                }

                $template = $this->registry->findForSend($code);
                if ($template === null) {
                    return false;
                }

                return ! $this->previewService->templateHasVariables($template);
            })
            ->map(function (array $option): array {
                $code = (string) $option['code'];
                $template = $this->registry->findForSend($code);
                $preview = is_array($option['preview'] ?? null) ? $option['preview'] : [];

                // Prefer live preview from the template row so CAMS/synced bodies
                // (often only on body_preview) are not lost if catalog preview is empty.
                if ($template !== null) {
                    $resolved = $this->previewService->forTemplate($template, [], true);
                    $body = trim((string) ($resolved['raw_body'] ?? $resolved['body'] ?? ''));
                    if ($body === '' || $body === (string) $template->name) {
                        $payloadBody = trim((string) ($template->wizardPayload()['body']['text'] ?? ''));
                        $storedPreview = trim((string) ($template->body_preview ?? ''));
                        $body = $payloadBody !== '' ? $payloadBody
                            : ($storedPreview !== '' && $storedPreview !== (string) $template->name ? $storedPreview : $body);
                    }

                    $preview = [
                        'body' => $body,
                        'footer' => (string) ($resolved['footer'] ?? $preview['footer'] ?? ''),
                        'header_type' => (string) ($resolved['header_type'] ?? $preview['header_type'] ?? 'none'),
                        'header_text' => (string) ($resolved['header_text'] ?? $preview['header_text'] ?? ''),
                        'header_image' => $resolved['header_image'] ?? $preview['header_image'] ?? null,
                        'header_video' => $resolved['header_video'] ?? $preview['header_video'] ?? null,
                        'buttons' => is_array($resolved['buttons'] ?? null)
                            ? $resolved['buttons']
                            : (is_array($preview['buttons'] ?? null) ? $preview['buttons'] : []),
                    ];
                }

                $option['preview'] = $preview;

                return $option;
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function mailLists(): Collection
    {
        return MailList::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (MailList $list): array => [
                'id' => (int) $list->id,
                'name' => (string) $list->name,
            ])
            ->values();
    }
}
