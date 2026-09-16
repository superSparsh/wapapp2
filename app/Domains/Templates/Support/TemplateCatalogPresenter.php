<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use App\Domains\Templates\Support\TemplateCategoryCatalog;
use App\Domains\Templates\Enums\TemplateSource;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\WhatsApp\Support\CamsComponentEncoder;
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
                $rejectionReason = CamsComponentEncoder::friendlyError($template->rejection_reason);

                return [
                'serial' => str_pad((string) ($offset + $index + 1), 2, '0', STR_PAD_LEFT),
                'name' => $template->name,
                'code' => (string) ($template->code ?? ''),
                'created_at' => $template->created_at?->format('Y-m-d h:i A') ?? '—',
                'type' => $isRegular ? 'Regular' : 'Draft',
                'type_variant' => $isRegular ? 'fd-type' : 'fd-draft',
                'category' => TemplateCategoryCatalog::listLabel(
                    (string) $template->category,
                    is_array($template->payload) ? $template->payload : []
                ),
                'category_variant' => match (strtoupper((string) (
                    TemplateCategoryCatalog::isCarousel((string) $template->category)
                        ? TemplateCategoryCatalog::MARKETING
                        : $template->category
                ))) {
                    'UTILITY' => 'fd-category-utility',
                    'AUTHENTICATION' => 'fd-category-auth',
                    'LIMITED_TIME_OFFER' => 'fd-category-lto',
                    default => 'fd-category-marketing',
                },
                'status' => $template->status->label(),
                'status_variant' => $template->status->chipVariant(),
                'error' => $isRejected,
                'rejection_reason' => $isRejected ? $rejectionReason : null,
                'rejection_title' => $isRejected ? 'Submission failed' : null,
                'preview_url' => route('templates.preview', array_filter([
                    'code' => $template->code,
                    'draft' => $template->code ? null : $template->uuid,
                    'preview' => 1,
                ])),
                'edit_url' => in_array($template->status, [TemplateStatus::Draft, TemplateStatus::PendingReview, TemplateStatus::Rejected], true)
                    ? route('templates.builder.body', $template)
                    : null,
                'copy_url' => route('templates.duplicate', $template),
                'uuid' => $template->uuid,
                'delete_url' => route('templates.destroy', $template),
                ];
            })
            ->all();
    }
}
