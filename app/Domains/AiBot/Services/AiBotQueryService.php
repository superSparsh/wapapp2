<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Domains\Integration\Services\PhoneLineService;
use App\Models\AiBot;
use App\Support\ListingSort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AiBotQueryService
{
    public function paginate(
        int $perPage = 10,
        ?string $search = null,
        string $sort = 'created_at',
        string $direction = 'desc',
        ?int $whatsappLineId = null,
    ): LengthAwarePaginator {
        $query = AiBot::query()->with('whatsappLine');

        $this->applyLineFilter($query, $whatsappLineId ?? PhoneLineService::lockedLineId());

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
     * Get active AI bots for a WhatsApp line (includes legacy null-line bots).
     *
     * @return Collection<int, AiBot>
     */
    public function activeBots(?int $whatsappLineId = null): Collection
    {
        $query = AiBot::query()
            ->active()
            ->orderByDesc('is_default');

        $this->applyLineFilter($query, $whatsappLineId);

        return $query->get();
    }

    public function findById(int $id): ?AiBot
    {
        return AiBot::query()->find($id);
    }

    public function findDefault(?int $whatsappLineId = null): ?AiBot
    {
        $query = AiBot::query()
            ->active()
            ->defaults();

        $this->applyLineFilter($query, $whatsappLineId);

        return $query->first();
    }

    /**
     * @param  Builder<AiBot>  $query
     */
    public function applyLineFilter(Builder $query, ?int $whatsappLineId): void
    {
        if ($whatsappLineId === null) {
            return;
        }

        $query->where(function (Builder $inner) use ($whatsappLineId): void {
            $inner->where('whatsapp_line_id', $whatsappLineId)
                ->orWhereNull('whatsapp_line_id');
        });
    }
}
