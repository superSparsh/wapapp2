<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Campaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CampaignRepositoryInterface
{
    public function findById(int $id): ?Campaign;

    public function findByUuid(string $uuid): ?Campaign;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Campaign;

    /**
     * @param array<string, mixed> $data
     */
    public function update(Campaign $campaign, array $data): Campaign;

    public function delete(Campaign $campaign): bool;

    public function paginate(
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): LengthAwarePaginator;

    /**
     * @return Collection<int, Campaign>
     */
    public function getDueScheduledCampaigns(): Collection;
}
