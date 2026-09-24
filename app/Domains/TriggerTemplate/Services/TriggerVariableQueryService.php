<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Services;

use App\Models\TriggerVariable;
use App\Support\ListingSort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TriggerVariableQueryService
{
    public function paginate(
        int $perPage = 10,
        string $sort = 'id',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        $query = TriggerVariable::query();

        ListingSort::apply($query, $sort, $direction, [
            'id' => 'id',
            'variable_name' => 'variable_name',
            'template_name' => 'template_name',
            'list_name' => 'list_name',
        ], 'id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return Collection<int, TriggerVariable>
     */
    public function forLine(int $lineId): Collection
    {
        return TriggerVariable::query()
            ->where(function ($query) use ($lineId): void {
                $query->whereNull('whatsapp_line_id')
                    ->orWhere('whatsapp_line_id', $lineId);
            })
            ->orderBy('id')
            ->get();
    }
}
