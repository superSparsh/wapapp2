<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Contracts\TemplateServiceClientInterface;
use App\Domains\Templates\Support\InteractiveMessagePresenter;
use App\Models\Template;
use App\Models\Variable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator as ConcreteLengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class TemplateServiceAdapter
{
    public function __construct(
        private readonly TemplateServiceClientInterface $client,
        private readonly TemplateCatalogService $localCatalogService,
        private readonly TemplateBuilderService $localBuilderService,
        private readonly TemplateRegistryService $localRegistryService,
        private readonly TemplatePreviewService $localPreviewService,
        private readonly TemplateVariableService $localVariableService,
        private readonly TemplateVariableQueryService $localVariableQueryService,
        private readonly BuiltinVariableCatalog $localBuiltinCatalog,
    ) {}

    public function isMicroserviceEnabled(): bool
    {
        return (bool) config('template-service.enabled', false);
    }

    private function shouldFallback(): bool
    {
        return (bool) config('template-service.fallback_to_local', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function indexViewData(
        ?string $search = null,
        ?string $category = null,
        ?string $type = null,
        int $page = 1,
        int $perPage = 10,
        ?string $code = null,
        ?string $draftUuid = null,
        bool $showPreviewParam = false,
        ?string $activeTab = 'approved',
        ?string $freeType = null,
        ?InteractiveMessageService $interactiveMessageService = null,
        ?InteractiveMessagePresenter $interactiveMessagePresenter = null,
    ): array {
        if ($this->isMicroserviceEnabled()) {
            try {
                $response = $this->client->listTemplates([
                    'q' => $search,
                    'category' => $category,
                    'type' => $type,
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

                $previewData = null;
                if (filled($code)) {
                    $previewData = $this->preview($code);
                } elseif (filled($draftUuid)) {
                    $tplResponse = $this->client->getTemplate($draftUuid);
                    $previewData = $tplResponse['preview'] ?? null;
                }

                $freeRows = [];
                $freeTypes = [];
                if ($interactiveMessageService && $interactiveMessagePresenter) {
                    $freeRows = $interactiveMessagePresenter->tableRows(
                        $interactiveMessageService->list($search !== '' ? $search : null, $freeType !== '' ? $freeType : null)
                    );
                    $freeTypes = $interactiveMessageService->types();
                }

                $total = (int) ($response['meta']['total'] ?? 0);

                $rows = collect($response['items'] ?? [])
                    ->map(fn (array $row, int $index): array => [
                        'serial' => $row['serial'] ?? str_pad((string) (($page - 1) * $perPage + $index + 1), 2, '0', STR_PAD_LEFT),
                        'name' => $row['name'] ?? '',
                        'code' => (string) ($row['code'] ?? ''),
                        'created_at' => $row['created_at'] ?? '—',
                        'type' => $row['type'] ?? 'Regular',
                        'category' => $row['category'] !== '' ? ($row['category'] ?? 'Marketing') : 'Marketing',
                        'status' => $row['status'] ?? 'Approved',
                        'status_variant' => $row['status_variant'] ?? 'fd-approved',
                        'error' => (bool) ($row['error'] ?? false),
                        'rejection_reason' => $row['rejection_reason'] ?? null,
                        'preview_url' => route('templates.preview', array_filter([
                            'code' => $row['code'] ?: null,
                            'draft' => ! empty($row['code']) ? null : ($row['uuid'] ?? null),
                            'preview' => 1,
                        ])),
                        'edit_url' => in_array($row['status_value'] ?? strtolower((string) ($row['status'] ?? '')), ['draft', 'pending_review', 'rejected'], true) && ! empty($row['uuid'])
                            ? route('templates.builder.body', ['template' => $row['uuid']])
                            : null,
                        'uuid' => $row['uuid'] ?? '',
                        'delete_url' => ! empty($row['uuid'])
                            ? route('templates.destroy', ['template' => $row['uuid']])
                            : null,
                    ])
                    ->all();

                return [
                    'activeTab' => $activeTab,
                    'templates' => $rows,
                    'freeTemplates' => $freeRows,
                    'categories' => $response['categories'] ?? $this->localCatalogService->categories(),
                    'types' => $response['types'] ?? $this->localCatalogService->types(),
                    'freeTypes' => $freeTypes,
                    'search' => $search,
                    'selectedCategory' => $category,
                    'selectedType' => $type,
                    'selectedFreeType' => $freeType,
                    'showPreview' => $showPreviewParam || $previewData !== null,
                    'previewData' => $previewData,
                    'pagination' => [
                        'total' => $total,
                        'per_page' => $perPage,
                        'current' => $page,
                        'pages' => max(1, (int) ceil($total / $perPage)),
                    ],
                ];
            } catch (Throwable $e) {
                Log::warning('Failed fetching templates from microservice, falling back to local', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        // Local fallback
        $previewData = null;
        if (filled($code)) {
            $previewData = $this->localPreviewService->forCode((string) $code);
        } elseif (filled($draftUuid)) {
            $draft = Template::query()->where('uuid', $draftUuid)->first();
            if ($draft instanceof Template) {
                $previewData = $this->localPreviewService->forTemplate($draft);
            }
        }

        $total = $this->localCatalogService->count(
            $search !== '' ? $search : null,
            $category !== '' ? $category : null,
            $type !== '' ? $type : null,
            approvedOnly: false,
        );

        $freeRows = [];
        $freeTypes = [];
        if ($interactiveMessageService && $interactiveMessagePresenter) {
            $freeRows = $interactiveMessagePresenter->tableRows(
                $interactiveMessageService->list($search !== '' ? $search : null, $freeType !== '' ? $freeType : null)
            );
            $freeTypes = $interactiveMessageService->types();
        }

        return [
            'activeTab' => $activeTab,
            'templates' => $this->localCatalogService->tableRows(
                $search !== '' ? $search : null,
                $category !== '' ? $category : null,
                $type !== '' ? $type : null,
                approvedOnly: false,
                page: $page,
                perPage: $perPage,
            ),
            'freeTemplates' => $freeRows,
            'categories' => $this->localCatalogService->categories(),
            'types' => $this->localCatalogService->types(),
            'freeTypes' => $freeTypes,
            'search' => $search,
            'selectedCategory' => $category,
            'selectedType' => $type,
            'selectedFreeType' => $freeType,
            'showPreview' => $showPreviewParam || $previewData !== null,
            'previewData' => $previewData,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current' => $page,
                'pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function options(): array
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                return $this->client->getOptions();
            } catch (Throwable $e) {
                Log::warning('Failed fetching template options from microservice, falling back to local', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localRegistryService->options();
    }

    public function preview(string $code): array
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                return $this->client->preview($code);
            } catch (Throwable $e) {
                Log::warning('Failed fetching template preview from microservice, falling back to local', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localPreviewService->forCode($code);
    }

    public function delete(Template $template): void
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $template->uuid ?? (string) $template->id;
                $this->client->deleteTemplate($uuid);
            } catch (Throwable $e) {
                Log::warning('Failed deleting template in microservice, falling back to local', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $template->delete();
    }

    public function bulkDelete(array $uuids): int
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                return $this->client->bulkDelete($uuids);
            } catch (Throwable $e) {
                Log::warning('Failed bulk deleting templates in microservice, falling back to local', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $templates = Template::query()->whereIn('uuid', $uuids)->get();
        $count = 0;
        foreach ($templates as $template) {
            $template->delete();
            $count++;
        }

        return $count;
    }

    public function variablesData(): array
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                return $this->client->allVariables();
            } catch (Throwable $e) {
                Log::warning('Failed fetching variables from microservice, falling back to local', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $custom = collect($this->localVariableQueryService->paginate(perPage: 100)->items())
            ->map(fn (Variable $variable): array => [
                'name' => $variable->name,
                'label' => $variable->name,
                'display_name' => ucwords(str_replace('_', ' ', $variable->name)),
                'description' => $variable->data_type->label().' variable',
                'syntax' => '$('.$variable->name.')',
                'type' => 'custom',
                'category' => 'custom',
                'data_type' => $variable->data_type->value,
            ])
            ->values()
            ->all();

        return [
            'custom' => $custom,
            'builtin' => $this->localBuiltinCatalog->all(),
        ];
    }
}
