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
use App\Support\ListingSort;
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
    public function options(?WhatsappLine $line = null, bool $sendableOnly = false): array
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
                $providerCode = $template->whatsappCode();
                $sendCode = $providerCode ?? (string) $template->code;

                return [
                    'code' => $sendCode,
                    'name' => $template->name,
                    'language' => $template->language,
                    'category' => $template->category,
                    'variables' => $variables,
                    'sendable' => $providerCode !== null || CamsTemplateIdentity::isProviderCode($sendCode),
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
            ->when(
                $sendableOnly,
                fn ($rows) => $rows->filter(fn (array $row): bool => (bool) ($row['sendable'] ?? false)),
            )
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Template>
     */
    public function listForTable(
        ?string $keyword = null,
        ?string $category = null,
        ?string $type = null,
        bool $approvedOnly = false,
        string $sort = 'updated_at',
        string $direction = 'desc',
    ): Collection {
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
            ->whereIn('status', $statuses);

        $keyword = trim((string) $keyword);
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('code', 'like', '%'.$keyword.'%');
            });
        }

        $category = trim((string) $category);
        if ($category !== '') {
            $this->scopeCategory($query, $category);
        }

        $type = trim((string) $type);
        if ($type === 'Regular') {
            $this->scopeRegularType($query);
        } elseif ($type === 'Draft') {
            $this->scopeDraftType($query);
        }

        ListingSort::apply($query, $sort, $direction, [
            'updated_at' => 'updated_at',
            'created_at' => 'created_at',
            'name' => 'name',
            'status' => 'status',
        ], 'updated_at');

        return $query->get();
    }

    /**
     * Regular = CAMS-synced OR has a provider TemplateCode (matches listing chip).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Template>  $query
     */
    private function scopeRegularType($query): void
    {
        $query->where(function ($builder): void {
            $builder->where('source', TemplateSource::Cams)
                ->orWhere(function ($inner): void {
                    $this->scopeProviderCode($inner);
                });
        });
    }

    /**
     * Draft = local source without a provider TemplateCode.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Template>  $query
     */
    private function scopeDraftType($query): void
    {
        $query->where('source', TemplateSource::Local)
            ->where(function ($builder): void {
                $builder->whereNull('code')
                    ->orWhere('code', '')
                    ->orWhere(function ($inner): void {
                        $inner->whereNotNull('code')
                            ->where('code', '!=', '')
                            ->whereRaw('NOT ('.$this->providerCodeSql('code').')');
                    });
            });
    }

    /**
     * Marketing filter includes carousel templates (stored as CAROUSEL or flag).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Template>  $query
     */
    private function scopeCategory($query, string $category): void
    {
        $category = strtoupper($category);

        if ($category === TemplateCategoryCatalog::MARKETING) {
            $query->where(function ($builder): void {
                $builder->where('category', TemplateCategoryCatalog::MARKETING)
                    ->orWhere('category', TemplateCategoryCatalog::CAROUSEL)
                    ->orWhere('payload->carousel->enabled', true);
            });

            return;
        }

        $query->where('category', $category);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Template>  $query
     */
    private function scopeProviderCode($query): void
    {
        $query->whereNotNull('code')
            ->where('code', '!=', '')
            ->whereRaw($this->providerCodeSql('code'));
    }

    private function providerCodeSql(string $column): string
    {
        $driver = Template::query()->getConnection()->getDriverName();

        // Alibaba TemplateCode: long numeric string (see CamsTemplateIdentity::isProviderCode).
        if ($driver === 'sqlite') {
            return "LENGTH(TRIM({$column})) >= 10 AND TRIM({$column}) GLOB '[0-9]*' AND INSTR(TRIM({$column}), ' ') = 0 AND INSTR(TRIM({$column}), '_legacy_') = 0";
        }

        return "TRIM({$column}) REGEXP '^[0-9]{10,}$'";
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
        // Same fallbacks as send/preview: line match → unassigned → any code.
        // Strict line-only lookup broke previews for legacy-imported templates
        // stored on another line (or with a null line) while a default line exists.
        return $this->findForSend($code, $line ?? $this->defaultLine());
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

        $byCode = $query->orderByDesc('id')->first();
        if ($byCode instanceof Template) {
            return $byCode;
        }

        // Provider TemplateCode may live in payload.meta.archived_code after soft archive.
        if (CamsTemplateIdentity::isProviderCode($code)) {
            $archived = Template::query()
                ->where('payload->meta->archived_code', $code)
                ->when(
                    $line instanceof WhatsappLine,
                    fn ($builder) => $builder->where(function ($inner) use ($line): void {
                        $inner->where('whatsapp_line_id', $line->id)->orWhereNull('whatsapp_line_id');
                    }),
                )
                ->orderByDesc('id')
                ->first();

            if ($archived instanceof Template) {
                return $archived;
            }
        }

        return null;
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
