<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;
use App\Repositories\Interfaces\CampaignRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CampaignQueryService
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaignRepo,
    ) {}

    public function paginate(
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        return $this->campaignRepo->paginate(
            perPage: $perPage,
            search: $search,
            status: $status,
            sort: $sort,
            direction: $direction,
        );
    }

    public function findByUuid(string $uuid): ?Campaign
    {
        return $this->campaignRepo->findByUuid($uuid);
    }

    public function findById(int $id): ?Campaign
    {
        return $this->campaignRepo->findById($id);
    }
}
