<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TemplateSource;
use App\Enums\TemplateStatus;
use App\Models\Template;
use Illuminate\Support\Collection;

class TemplateCatalogPresenter
{
    /**
     * @param  Collection<int, Template>  $templates
     * @param  int  $page  Current page (1-based)
     * @param  int  $perPage  Items per page
     * @return list<array<string, mixed>>
     */
    public function tableRows(Collection $templates, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;

        return $templates
            ->slice($offset, $perPage)
            ->values()
            ->map(function (Template $template, int $index) use ($offset): array {
                $isRegular = $template->source === TemplateSource::Cams
                    || filled($template->whatsappCode());

                $isRejected = $template->status === TemplateStatus::Rejected;
                $error = $isRejected
                    ? CamsComponentEncoder::presentError($template->rejection_reason)
                    : null;

                return [
                    'serial' => str_pad((string) ($offset + $index + 1), 2, '0', STR_PAD_LEFT),
                    'id' => $template->id,
                    'uuid' => $template->uuid,
                    'name' => $template->name,
                    'code' => (string) ($template->code ?? ''),
                    'created_at' => $template->created_at?->format('Y-m-d h:i A') ?? '—',
                    'type' => $isRegular ? 'Regular' : 'Draft',
                    'category' => $template->category !== '' ? $template->category : 'Marketing',
                    'status' => $template->status->label(),
                    'status_value' => $template->status->value,
                    'status_variant' => $template->status->chipVariant(),
                    'error' => $isRejected,
                    'rejection_title' => $error['title'] ?? null,
                    'rejection_reason' => $error['message'] ?? $template->rejection_reason,
                    'rejection_hint' => $error['hint'] ?? null,
                ];
            })
            ->all();
    }
}
