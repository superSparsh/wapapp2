<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\Templates\Enums\TemplateSource;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Domains\Templates\Support\TemplateCatalogCache;
use App\Domains\Templates\Support\TemplateCategoryCatalog;
use App\Domains\Templates\Support\VariableActorContext;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TemplateRegistryService
{
    public function __construct(
        private readonly InboxOutboundService $outboundService,
        private readonly VariableActorContext $actorContext,
    ) {}

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     language: string,
     *     category: string,
     *     variables: list<array{name: string}>,
     *     preview: array<string, mixed>
     * }>
     */
    public function options(?WhatsappLine $line = null): array
    {
        $line ??= $this->defaultLine();

        // Resolved lazily to avoid a constructor cycle with TemplatePreviewService.
        $previewService = app(TemplatePreviewService::class);

        $query = Template::query()
            ->where('status', TemplateStatus::Approved)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->with('variables')
            ->orderBy('name');

        $templates = (clone $query)
            ->when(
                $line instanceof WhatsappLine,
                function ($builder) use ($line): void {
                    $builder->where(function ($inner) use ($line): void {
                        $inner->where('whatsapp_line_id', $line->id)
                            ->orWhereNull('whatsapp_line_id');
                    });
                },
            )
            ->get();

        // Legacy import often stores approved templates on another line, or with a null line.
        // Inbox should still list them the way the Templates page does.
        if ($templates->isEmpty()) {
            $templates = $query->get();
        }

        return $templates
            ->map(function (Template $template) use ($previewService): array {
                $variables = array_values(array_map(
                    static fn (array $variable): array => ['name' => (string) $variable['name']],
                    $previewService->variablesForTemplate($template),
                ));
                $preview = $previewService->forTemplate($template, [], true);
                $sendCode = $template->whatsappCode() ?? (string) $template->code;

                return [
                    'code' => $sendCode,
                    'name' => $template->name,
                    'language' => $template->language,
                    'category' => $template->category,
                    'variables' => $variables,
                    'preview' => [
                        'body' => (string) ($preview['raw_body'] ?? $preview['body'] ?? ''),
                        'footer' => (string) ($preview['footer'] ?? ''),
                        'header_type' => (string) ($preview['header_type'] ?? 'none'),
                        'header_text' => (string) ($preview['header_text'] ?? ''),
                        'header_image' => $preview['header_image'] ?? null,
                        'header_video' => $preview['header_video'] ?? null,
                        'buttons' => is_array($preview['buttons'] ?? null) ? $preview['buttons'] : [],
                    ],
                ];
            })
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
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->map(function (mixed $category): string {
                $value = strtoupper((string) $category);

                // Carousel drafts → Marketing filter (legacy list)
                return TemplateCategoryCatalog::isCarousel($value)
                    ? TemplateCategoryCatalog::MARKETING
                    : $value;
            })
            ->unique()
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

    public function findForSend(string $code, ?WhatsappLine $line = null): ?Template
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $query = Template::query()->where('code', $code);

        if ($line instanceof WhatsappLine) {
            $onLine = (clone $query)->where('whatsapp_line_id', $line->id)->first();
            if ($onLine instanceof Template) {
                return $onLine;
            }

            $unassigned = (clone $query)->whereNull('whatsapp_line_id')->first();
            if ($unassigned instanceof Template) {
                return $unassigned;
            }
        }

        return $query->orderByDesc('id')->first();
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
        $syncService = app(TemplateSyncService::class);

        DB::transaction(function () use ($items, $line, $syncService): void {
            foreach ($items as $item) {
                $code = (string) ($item['code'] ?? '');

                if ($code === '') {
                    continue;
                }

                [$status] = $syncService->mapAuditStatus(
                    filled($item['audit_status'] ?? null) ? (string) $item['audit_status'] : 'pass',
                    filled($item['reason'] ?? null) ? (string) $item['reason'] : null,
                );

                Template::query()->updateOrCreate(
                    [
                        'code' => $code,
                        'whatsapp_line_id' => $line->id,
                    ],
                    [
                        'name' => (string) ($item['name'] ?? $code),
                        'language' => (string) ($item['language'] ?? 'en_GB'),
                        'category' => (string) ($item['category'] ?? 'MARKETING'),
                        'status' => $status,
                        'source' => TemplateSource::Cams,
                        'synced_at' => now(),
                        'rejection_reason' => $status === TemplateStatus::Rejected
                            ? Str::limit((string) ($item['reason'] ?? ''), 500)
                            : null,
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
