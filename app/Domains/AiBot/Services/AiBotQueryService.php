<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Models\AiBot;
use App\Support\ListingSort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AiBotQueryService
{
    public function paginate(
        int $perPage = 10,
        ?string $search = null,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        $query = AiBot::query();

        if ($search !== null && $search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        ListingSort::apply($query, $sort, $direction, [
            'created_at' => 'created_at',
            'name' => 'name',
            'status' => 'status',
        ], 'created_at');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Get all active AI bots (cached for the request).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AiBot>
     */
    public function activeBots(): \Illuminate\Database\Eloquent\Collection
    {
        return AiBot::query()
            ->active()
            ->orderByDesc('is_default')
            ->get();
    }

    public function findById(int $id): ?AiBot
    {
        return AiBot::query()->find($id);
    }

    public function findDefault(): ?AiBot
    {
        return AiBot::query()
            ->active()
            ->defaults()
            ->first();
    }
}
