<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Services;

use App\Enums\WhatsappFlowStatus;
use App\Models\WhatsappFlow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WhatsappFlowQueryService
{
    public function paginate(
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        $query = WhatsappFlow::query()
            ->withCount('submissions');

        if ($search !== null && $search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status !== null && $status !== '') {
            $enum = WhatsappFlowStatus::tryFrom($status);

            if ($enum !== null) {
                $query->where('status', $enum);
            }
        }

        $allowedSorts = ['created_at', 'name', 'updated_at', 'published_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        return $query
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findById(int $id): ?WhatsappFlow
    {
        return WhatsappFlow::query()->find($id);
    }

    public function findByMetaFlowId(string $metaFlowId): ?WhatsappFlow
    {
        return WhatsappFlow::query()
            ->where('meta_flow_id', $metaFlowId)
            ->first();
    }

    public function findByDataExchangeToken(string $token): ?WhatsappFlow
    {
        return WhatsappFlow::query()
            ->where(function ($query) use ($token): void {
                $query->where('exchange_token', $token)
                    ->orWhere('data_exchange_endpoint', 'like', "%{$token}");
            })
            ->active()
            ->first();
    }

    /**
     * @return Collection<int, WhatsappFlow>
     */
    public function activeFlows(): Collection
    {
        return WhatsappFlow::query()
            ->active()
            ->whereNotNull('published_at')
            ->orderByDesc('id')
            ->get();
    }
}
