<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Variable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface VariableRepositoryInterface
{
    public function paginate(?string $keyword = null, ?int $whatsappLineId = null, int $perPage = 10, int $page = 1): LengthAwarePaginator;

    public function all(?string $keyword = null, ?int $whatsappLineId = null): Collection;

    public function findById(int $id): ?Variable;

    public function findByUuid(string $uuid): ?Variable;

    public function findByName(string $name, ?int $whatsappLineId = null): ?Variable;

    public function create(array $attributes): Variable;

    public function update(Variable $variable, array $attributes): Variable;

    public function delete(Variable $variable): bool;
}
