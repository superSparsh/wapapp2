<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Support\TemplateCatalogPresenter;
use App\Models\Template;
use Illuminate\Support\Collection;

class TemplateCatalogService
{
    public function __construct(
        private readonly TemplateRegistryService $registry,
        private readonly TemplateCatalogPresenter $presenter,
    ) {}

    /**
     * @return Collection<int, Template>
     */
    public function filtered(?string $keyword = null, ?string $category = null, ?string $type = null, bool $approvedOnly = false): Collection
    {
        return $this->registry->listForTable($keyword, $category, $type, $approvedOnly);
    }

    /** @return list<string> */
    public function types(): array
    {
        return $this->registry->types();
    }

    /** @return list<string> */
    public function categories(): array
    {
        return $this->registry->categories();
    }

    public function refresh(): int
    {
        return $this->registry->refresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tableRows(?string $keyword = null, ?string $category = null, ?string $type = null, bool $approvedOnly = false, int $page = 1, int $perPage = 10): array
    {
        return $this->presenter->tableRows($this->filtered($keyword, $category, $type, $approvedOnly), $page, $perPage);
    }

    /**
     * Count total templates matching the filter.
     */
    public function count(?string $keyword = null, ?string $category = null, ?string $type = null, bool $approvedOnly = false): int
    {
        return $this->filtered($keyword, $category, $type, $approvedOnly)->count();
    }
}
