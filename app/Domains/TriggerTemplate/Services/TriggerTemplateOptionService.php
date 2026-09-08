<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Services;

use App\Domains\Templates\Services\TemplateRegistryService;
use Illuminate\Support\Collection;

class TriggerTemplateOptionService
{
    public function __construct(
        private readonly TemplateRegistryService $registry,
    ) {}

    /**
     * @return array<int, array{code: string, name: string, language: string, category: string}>
     */
    public function templates(): array
    {
        return $this->registry->options();
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function mailLists(): Collection
    {
        return collect();
    }
}
