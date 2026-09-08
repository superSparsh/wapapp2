<?php

declare(strict_types=1);

namespace App\Repositories\Implementations;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Repositories\Interfaces\CampaignRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CampaignRepository implements CampaignRepositoryInterface
{
    public function findById(int $id): ?Campaign
    {
        return Campaign::query()->find($id);
    }

    public function findByUuid(string $uuid): ?Campaign
    {
        return Campaign::query()->where('uuid', $uuid)->first();
    }

    public function create(array $data): Campaign
    {
        return Campaign::query()->create($data);
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        $campaign->update($data);

        return $campaign->refresh();
    }

    public function delete(Campaign $campaign): bool
    {
        return (bool) $campaign->delete();
    }

    public function paginate(
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        $query = Campaign::query();

        if ($search !== null && $search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status !== null && $status !== '') {
            $enum = CampaignStatus::tryFrom($status);
            if ($enum !== null) {
                $query->where('status', $enum);
            }
        }

        $allowedSorts = (array) config('campaigns.sort_columns', ['name', 'created_at', 'total_recipients', 'scheduled_at', 'status']);
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        return $query->orderBy($sort, $direction)->paginate($perPage);
    }

    public function getDueScheduledCampaigns(): Collection
    {
        return Campaign::query()
            ->where('status', CampaignStatus::Scheduled)
            ->where('scheduled_at', '<=', now())
            ->get();
    }
}
