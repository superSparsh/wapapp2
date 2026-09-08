<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Models\AiBot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AiBotQueryService
{
    public function paginate(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        $query = AiBot::query()->orderByDesc('created_at');

        if ($search !== null && $search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->paginate($perPage);
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
