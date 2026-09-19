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
