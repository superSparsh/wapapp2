<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Template;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TemplateRepositoryInterface
{
    public function paginate(
        ?string $keyword = null,
        ?string $category = null,
        ?string $type = null,
        bool $approvedOnly = false,
        ?int $whatsappLineId = null,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator;

    public function list(
        ?string $keyword = null,
        ?string $category = null,
        ?string $type = null,
        bool $approvedOnly = false,
        ?int $whatsappLineId = null,
    ): Collection;

    public function findById(int $id): ?Template;

    public function findByUuid(string $uuid): ?Template;

    public function findByCode(string $code): ?Template;

    public function create(array $attributes): Template;

    public function update(Template $template, array $attributes): Template;

    public function delete(Template $template): bool;

    public function bulkDelete(array $uuids): int;

    public function approvedOptions(?int $whatsappLineId = null): array;
}
