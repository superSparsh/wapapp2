<?php

declare(strict_types=1);

namespace App\Domains\Drip\Services;

use App\Enums\ChatbotFlowStatAction;
use App\Enums\ChatbotFlowStatus;
use App\Models\DripCampaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DripCampaignQueryService
{
    public function paginate(
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        $query = DripCampaign::query()
            ->with('audience:id,name')
            ->withCount('states')
            ->withCount('stats')
            ->withCount(['stats as completed_stats_count' => fn ($q) => $q->where('action', ChatbotFlowStatAction::Completed)]);

        if ($search !== null && $search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status !== null && $status !== '') {
            $enum = ChatbotFlowStatus::tryFrom($status);
            if ($enum !== null) {
                $query->where('status', $enum);
            }
        }

        $allowedSorts = ['created_at', 'name', 'stats_count', 'states_count'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        return $query->orderBy($sort, $direction)->paginate($perPage)->withQueryString();
    }

    public function findById(int|string $id): ?DripCampaign
    {
        return DripCampaign::query()
            ->with('audience')
            ->withCount('states')
            ->withCount('stats')
            ->where('uuid', $id)
            ->first();
    }

    public function resolveBinding(int|string $id): ?DripCampaign
    {
        return $this->findById($id);
    }
}
