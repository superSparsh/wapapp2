<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Services;

use App\Models\TriggerVariable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TriggerVariableQueryService
{
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return TriggerVariable::query()
            ->orderByDesc('id')
            ->paginate($perPage);
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
