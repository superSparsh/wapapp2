<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Services;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Services\TemplatePreviewService;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Models\MailList;
use App\Models\Template;
use Illuminate\Support\Collection;

class TriggerTemplateOptionService
{
    public function __construct(
        private readonly TemplatePreviewService $previewService,
    ) {}

    /**
     * All approved templates (latest first), including those with body variables.
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
        return Template::query()
            ->where('status', TemplateStatus::Approved)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->with('variables')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (Template $template): array {
                $preview = $this->previewService->forTemplate($template, [], true);
                $body = trim((string) ($preview['raw_body'] ?? $preview['body'] ?? ''));
                if ($body === '' || $body === (string) $template->name) {
                    $payloadBody = trim((string) ($template->wizardPayload()['body']['text'] ?? ''));
                    $storedPreview = trim((string) ($template->body_preview ?? ''));
                    $body = $payloadBody !== '' ? $payloadBody
                        : ($storedPreview !== '' && $storedPreview !== (string) $template->name ? $storedPreview : $body);
                }

                $providerCode = $template->whatsappCode();
                $sendCode = $providerCode ?? (string) $template->code;

                return [
                    'code' => $sendCode,
                    'name' => (string) $template->name,
                    'language' => (string) $template->language,
                    'category' => (string) $template->category,
                    'variables' => collect($this->previewService->variablesForTemplate($template))
                        ->map(fn (array $variable): string => trim((string) ($variable['name'] ?? '')))
                        ->filter(fn (string $name): bool => $name !== '')
                        ->unique()
                        ->values()
                        ->all(),
                    'preview' => [
                        'body' => $body,
                        'footer' => (string) ($preview['footer'] ?? ''),
                        'header_type' => (string) ($preview['header_type'] ?? 'none'),
                        'header_text' => (string) ($preview['header_text'] ?? ''),
                        'header_image' => $preview['header_image'] ?? null,
                        'header_video' => $preview['header_video'] ?? null,
                        'buttons' => is_array($preview['buttons'] ?? null) ? $preview['buttons'] : [],
                    ],
                    'sendable' => $providerCode !== null || CamsTemplateIdentity::isProviderCode($sendCode),
                ];
            })
            ->filter(fn (array $row): bool => $row['code'] !== '')
            // Prefer unique codes; keep the newest row when duplicates exist.
            ->unique('code')
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function mailLists(): Collection
    {
        return MailList::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'name'])
            ->map(fn (MailList $list): array => [
                'id' => (int) $list->id,
                'name' => (string) $list->name,
            ])
            ->values();
    }
}
