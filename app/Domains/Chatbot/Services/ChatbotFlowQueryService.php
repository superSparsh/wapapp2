<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services;

use App\Enums\ChatbotFlowStatus;
use App\Models\ChatbotFlow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ChatbotFlowQueryService
{
    public function paginate(
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        $query = ChatbotFlow::query()
            ->withCount('states');

        if ($search !== null && $search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status !== null && $status !== '') {
            $enum = ChatbotFlowStatus::tryFrom($status);

            if ($enum !== null) {
                $query->where('status', $enum);
            }
        }

        $allowedSorts = ['created_at', 'name', 'updated_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        return $query
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, ChatbotFlow>
     */
    public function forLine(int $lineId): Collection
    {
        return ChatbotFlow::query()
            ->active()
            ->where(function ($query) use ($lineId): void {
                $query->whereNull('whatsapp_line_id')
                    ->orWhere('whatsapp_line_id', $lineId);
            })
            ->orderByDesc('id')
            ->get();
    }

    public function findById(int $id): ?ChatbotFlow
    {
        return ChatbotFlow::query()->find($id);
    }

    public function findByUuid(string $uuid): ?ChatbotFlow
    {
        return ChatbotFlow::query()->where('uuid', $uuid)->first();
    }

    /**
     * @return Collection<int, ChatbotFlow>
     */
    public function activeFlows(): Collection
    {
        return ChatbotFlow::query()
            ->active()
            ->published()
            ->orderByDesc('id')
            ->get();
    }
}
