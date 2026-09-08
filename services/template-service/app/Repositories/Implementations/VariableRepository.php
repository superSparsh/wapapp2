<?php

declare(strict_types=1);

namespace App\Repositories\Implementations;

use App\Models\Variable;
use App\Repositories\Interfaces\VariableRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class VariableRepository implements VariableRepositoryInterface
{
    public function paginate(?string $keyword = null, ?int $whatsappLineId = null, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        return $this->buildQuery($keyword, $whatsappLineId)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function all(?string $keyword = null, ?int $whatsappLineId = null): Collection
    {
        return $this->buildQuery($keyword, $whatsappLineId)->get();
    }

    public function findById(int $id): ?Variable
    {
        return Variable::query()->find($id);
    }

    public function findByUuid(string $uuid): ?Variable
    {
        return Variable::query()->where('uuid', $uuid)->first();
    }

    public function findByName(string $name, ?int $whatsappLineId = null): ?Variable
    {
        return $this->buildQuery(null, $whatsappLineId)->where('name', $name)->first();
    }

    public function create(array $attributes): Variable
    {
        return Variable::query()->create($attributes);
    }

    public function update(Variable $variable, array $attributes): Variable
    {
        $variable->update($attributes);

        return $variable->refresh();
    }

    public function delete(Variable $variable): bool
    {
        return (bool) $variable->delete();
    }

    private function buildQuery(?string $keyword = null, ?int $whatsappLineId = null): Builder
    {
        $query = Variable::query()->orderByDesc('id');

        if ($whatsappLineId !== null) {
            $query->where('whatsapp_line_id', $whatsappLineId);
        }

        $keyword = trim((string) $keyword);
        if ($keyword !== '') {
            $query->where('name', 'like', '%'.$keyword.'%');
        }

        return $query;
    }
}
