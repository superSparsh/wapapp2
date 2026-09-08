<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\Templates\Enums\TemplateSource;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Support\TemplateCatalogCache;
use App\Domains\Templates\Support\VariableActorContext;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TemplateRegistryService
{
    public function __construct(
        private readonly InboxOutboundService $outboundService,
        private readonly VariableActorContext $actorContext,
    ) {}

    /**
     * @return list<array{code: string, name: string, language: string, category: string}>
     */
    public function options(?WhatsappLine $line = null): array
    {
        $line ??= $this->defaultLine();

        if (! $line instanceof WhatsappLine) {
            return [];
        }

        return Template::query()
            ->where('whatsapp_line_id', $line->id)
            ->where('status', TemplateStatus::Approved)
            ->orderBy('name')
            ->get(['code', 'name', 'language', 'category'])
            ->map(fn (Template $template): array => [
                'code' => (string) $template->code,
                'name' => $template->name,
                'language' => $template->language,
                'category' => $template->category,
            ])
            ->filter(fn (array $row): bool => $row['code'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Template>
     */
    public function listForTable(?string $keyword = null, ?string $category = null, ?string $type = null, bool $approvedOnly = false): Collection
    {
        // Drop CAMS clones that shadow a legacy/local template (same name + line).
        $this->pruneCamsDuplicatesOfLocal();

        $statuses = $approvedOnly
            ? [TemplateStatus::Approved]
            : [
                TemplateStatus::Approved,
                TemplateStatus::Draft,
                TemplateStatus::PendingReview,
                TemplateStatus::Rejected,
            ];

        // Legacy-imported + locally created templates only. CAMS sync is explicit via refresh().
        $query = Template::query()
            ->whereIn('status', $statuses)
            ->orderByDesc('updated_at');

        $keyword = trim((string) $keyword);
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('code', 'like', '%'.$keyword.'%');
            });
        }

        $category = trim((string) $category);
        if ($category !== '') {
            $query->where('category', $category);
        }

        $type = trim((string) $type);
        if ($type === 'Regular') {
            $query->where('source', TemplateSource::Cams);
        } elseif ($type === 'Draft') {
            $query->where('source', TemplateSource::Local);
        }

        return $query->get();
    }

    /** @return list<string> */
    public function types(): array
    {
        return ['Regular', 'Draft'];
    }

    /** @return list<string> */
    public function categories(): array
    {
        return Template::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values()
            ->all();
    }

    public function findByCode(string $code, ?WhatsappLine $line = null): ?Template
    {
        $line ??= $this->defaultLine();

        return Template::query()
            ->where('code', $code)
            ->when($line, fn ($query) => $query->where('whatsapp_line_id', $line->id))
            ->first();
    }

    public function refresh(?WhatsappLine $line = null): int
    {
        TemplateCatalogCache::flush();
        $line ??= $this->defaultLine();

        return $line instanceof WhatsappLine ? $this->syncLine($line) : 0;
    }

    public function syncLine(WhatsappLine $line): int
    {
        $items = $this->outboundService->listTemplates($line);

        DB::transaction(function () use ($items, $line): void {
            foreach ($items as $item) {
                $code = (string) ($item['code'] ?? '');

                if ($code === '') {
                    continue;
                }

                Template::query()->updateOrCreate(
                    [
                        'code' => $code,
                        'whatsapp_line_id' => $line->id,
                    ],
                    [
                        'name' => (string) ($item['name'] ?? $code),
                        'language' => (string) ($item['language'] ?? 'en_GB'),
                        'category' => (string) ($item['category'] ?? 'MARKETING'),
                        'status' => TemplateStatus::Approved,
                        'source' => TemplateSource::Cams,
                        'synced_at' => now(),
                        'body_preview' => (string) ($item['body'] ?? $item['name'] ?? $code),
                    ],
                );
            }
        });

        return count($items);
    }

    /**
     * Remove CAMS-synced rows that duplicate a legacy/local template (same name + line).
     */
    public function pruneCamsDuplicatesOfLocal(): int
    {
        $deleted = 0;

        Template::query()
            ->where('source', TemplateSource::Local)
            ->orderBy('id')
            ->get(['id', 'name', 'whatsapp_line_id'])
            ->each(function (Template $local) use (&$deleted): void {
                $query = Template::query()
                    ->where('source', TemplateSource::Cams)
                    ->where('name', $local->name);

                if ($local->whatsapp_line_id !== null) {
                    $query->where('whatsapp_line_id', $local->whatsapp_line_id);
                }

                $ids = $query->pluck('id');
                if ($ids->isEmpty()) {
                    return;
                }

                $deleted += Template::query()->whereIn('id', $ids)->forceDelete();
            });

        return $deleted;
    }

    private function defaultLine(): ?WhatsappLine
    {
        return WhatsappLine::query()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
