<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Support\InteractiveMessagePresenter;
use App\Models\Template;
use App\Models\Variable;

class TemplateServiceAdapter
{
    public function __construct(
        private readonly TemplateCatalogService $localCatalogService,
        private readonly TemplateBuilderService $localBuilderService,
        private readonly TemplateRegistryService $localRegistryService,
        private readonly TemplatePreviewService $localPreviewService,
        private readonly TemplateVariableService $localVariableService,
        private readonly TemplateVariableQueryService $localVariableQueryService,
        private readonly BuiltinVariableCatalog $localBuiltinCatalog,
    ) {}

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
        string $sort = 'updated_at',
        string $direction = 'desc',
    ): array {
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
                $interactiveMessageService->list(
                    $search !== '' ? $search : null,
                    $freeType !== '' ? $freeType : null,
                    $sort,
                    $direction,
                )
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
                sort: $sort,
                direction: $direction,
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
            'currentSort' => $sort,
            'currentDirection' => $direction,
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
        return $this->localRegistryService->options();
    }

    public function preview(string $code): array
    {
        return $this->localPreviewService->forCode($code);
    }

    public function delete(Template $template): void
    {
        $template->delete();
    }

    public function bulkDelete(array $uuids): int
    {
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
