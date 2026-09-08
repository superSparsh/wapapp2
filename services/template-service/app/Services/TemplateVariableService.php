<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VariableDataType;
use App\Enums\VariableType;
use App\Models\Variable;
use App\Repositories\Interfaces\VariableRepositoryInterface;
use App\Support\VariableActorContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TemplateVariableService
{
    public function __construct(
        private readonly VariableRepositoryInterface $variableRepository,
        private readonly VariableActorContext $actorContext,
    ) {}

    public function paginate(?string $keyword = null, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        return $this->variableRepository->paginate(
            $keyword,
            $this->actorContext->whatsappLineId(),
            $perPage,
            $page,
        );
    }

    public function all(?string $keyword = null): Collection
    {
        return $this->variableRepository->all(
            $keyword,
            $this->actorContext->whatsappLineId(),
        );
    }

    public function findByUuid(string $uuid): ?Variable
    {
        return $this->variableRepository->findByUuid($uuid);
    }

    /**
     * @param array{
     *     name: string,
     *     data_type: VariableDataType|string,
     *     type?: VariableType|string,
     *     value?: string|null,
     * } $data
     */
    public function create(array $data): Variable
    {
        return DB::transaction(function () use ($data): Variable {
            $dataType = $data['data_type'] instanceof VariableDataType
                ? $data['data_type']
                : VariableDataType::from((string) $data['data_type']);

            $type = isset($data['type'])
                ? ($data['type'] instanceof VariableType ? $data['type'] : VariableType::from((string) $data['type']))
                : VariableType::Dynamic;

            return $this->variableRepository->create([
                'type' => $type,
                'name' => $this->normalizeName((string) $data['name']),
                'data_type' => $dataType,
                'value' => $data['value'] ?? null,
                'whatsapp_line_id' => $this->actorContext->whatsappLineId(),
                'team_member_id' => $this->actorContext->teamMemberId(),
                'team_member_name' => $this->actorContext->teamMemberName(),
            ]);
        });
    }

    /**
     * @param array{
     *     name: string,
     *     data_type: VariableDataType|string,
     *     value?: string|null,
     * } $data
     */
    public function update(Variable $variable, array $data): Variable
    {
        return DB::transaction(function () use ($variable, $data): Variable {
            $dataType = $data['data_type'] instanceof VariableDataType
                ? $data['data_type']
                : VariableDataType::from((string) $data['data_type']);

            return $this->variableRepository->update($variable, [
                'name' => $this->normalizeName((string) $data['name']),
                'data_type' => $dataType,
                'value' => $data['value'] ?? $variable->value,
            ]);
        });
    }

    public function delete(Variable $variable): bool
    {
        return $this->variableRepository->delete($variable);
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)->trim()->lower()->replace(' ', '_')->toString();
    }
}
