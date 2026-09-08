<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use App\Domains\Templates\Enums\TemplateStatus;
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
            ->map(fn (Template $template, int $index): array => [
                'serial' => str_pad((string) ($offset + $index + 1), 2, '0', STR_PAD_LEFT),
                'name' => $template->name,
                'code' => (string) ($template->code ?? ''),
                'created_at' => $template->created_at?->format('Y-m-d h:i A') ?? '—',
                'type' => $template->source->value === 'cams' ? 'Regular' : 'Draft',
                'category' => $template->category !== '' ? $template->category : 'Marketing',
                'status' => $template->status->label(),
                'status_variant' => $template->status->chipVariant(),
                'error' => $template->status === TemplateStatus::Rejected,
                'rejection_reason' => $template->rejection_reason,
                'preview_url' => route('templates.preview', array_filter([
                    'code' => $template->code,
                    'draft' => $template->code ? null : $template->uuid,
                    'preview' => 1,
                ])),
                'edit_url' => in_array($template->status, [TemplateStatus::Draft, TemplateStatus::PendingReview, TemplateStatus::Rejected], true)
                    ? route('templates.builder.body', $template)
                    : null,
                'uuid' => $template->uuid,
                'delete_url' => route('templates.destroy', $template),
            ])
            ->all();
    }
}
