<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CampaignRecipientRepositoryInterface
{
    public function findById(int $id): ?CampaignRecipient;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): CampaignRecipient;

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function insertBatch(array $rows): int;

    public function paginateForCampaign(
        Campaign $campaign,
        int $perPage = 10,
        ?string $status = null,
    ): LengthAwarePaginator;

    /**
     * @return array{total: int, pending: int, sent: int, delivered: int, failed: int, read: int, response: int, unsubscribed: int}
     */
    public function getAggregateStats(Campaign $campaign): array;
}
