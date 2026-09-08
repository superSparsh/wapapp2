<?php

declare(strict_types=1);

namespace App\Repositories\Implementations;

use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Repositories\Interfaces\CampaignRecipientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CampaignRecipientRepository implements CampaignRecipientRepositoryInterface
{
    public function findById(int $id): ?CampaignRecipient
    {
        return CampaignRecipient::query()->find($id);
    }

    public function create(array $data): CampaignRecipient
    {
        return CampaignRecipient::query()->create($data);
    }

    public function insertBatch(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = now();
        $formatted = array_map(function (array $row) use ($now) {
            if (! isset($row['created_at'])) {
                $row['created_at'] = $now;
            }
            if (! isset($row['updated_at'])) {
                $row['updated_at'] = $now;
            }
            if (isset($row['variable_values']) && is_array($row['variable_values'])) {
                $row['variable_values'] = json_encode($row['variable_values']);
            }

            return $row;
        }, $rows);

        CampaignRecipient::query()->insert($formatted);

        return count($formatted);
    }

    public function paginateForCampaign(
        Campaign $campaign,
        int $perPage = 10,
        ?string $status = null,
    ): LengthAwarePaginator {
        $query = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->orderByDesc('created_at');

        if ($status !== null && $status !== '') {
            $enum = CampaignRecipientStatus::tryFrom($status);
            if ($enum !== null) {
                $query->where('status', $enum);
            }
        }

        return $query->paginate($perPage);
    }

    public function getAggregateStats(Campaign $campaign): array
    {
        $stats = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = ? THEN 1 END) as pending,
                COUNT(CASE WHEN status = ? THEN 1 END) as sent,
                COUNT(CASE WHEN status = ? THEN 1 END) as delivered,
                COUNT(CASE WHEN status = ? THEN 1 END) as failed,
                COUNT(CASE WHEN status = ? THEN 1 END) as `read`,
                COUNT(CASE WHEN status = ? THEN 1 END) as response,
                COUNT(CASE WHEN status = ? THEN 1 END) as unsubscribed
            ", [
                CampaignRecipientStatus::Pending->value,
                CampaignRecipientStatus::Sent->value,
                CampaignRecipientStatus::Delivered->value,
                CampaignRecipientStatus::Failed->value,
                CampaignRecipientStatus::Read->value,
                CampaignRecipientStatus::Response->value,
                CampaignRecipientStatus::Unsubscribed->value,
            ])
            ->first();

        return [
            'total' => (int) ($stats->total ?? 0),
            'pending' => (int) ($stats->pending ?? 0),
            'sent' => (int) ($stats->sent ?? 0),
            'delivered' => (int) ($stats->delivered ?? 0),
            'failed' => (int) ($stats->failed ?? 0),
            'read' => (int) ($stats->read ?? 0),
            'response' => (int) ($stats->response ?? 0),
            'unsubscribed' => (int) ($stats->unsubscribed ?? 0),
        ];
    }
}
