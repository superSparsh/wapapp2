<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CampaignQueryService
{
    /**
     * Paginate campaigns with eager loading and optimized selects.
     * Uses denormalized counters to avoid COUNT subqueries on index.
     */
    public function paginate(
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        $query = Campaign::query()
            ->with('audience:id,name')
            ->with('whatsappLine:id,phone,display_name')
            ->with('template:id,name')
            // Select only display columns — skip heavy JSON fields
            ->select([
                'id', 'uuid', 'name', 'status', 'audience_id',
                'whatsapp_line_id', 'template_id', 'scheduled_at',
                'timezone', 'total_recipients', 'total_delivered',
                'total_failed', 'total_read', 'total_response', 'total_unsubscribed',
                'created_at', 'updated_at',
            ]);

        // Search filter
        if ($search !== null && $search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        // Status filter
        if ($status !== null && $status !== '') {
            $enum = CampaignStatus::tryFrom($status);
            if ($enum !== null) {
                $query->where('status', $enum);
            }
        }

        // Sorting — only allow safe columns (whitelist from config)
        $allowedSorts = config('campaigns.sort_columns', ['name', 'created_at', 'total_recipients', 'scheduled_at', 'status']);
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        return $query->orderBy($sort, $direction)->paginate($perPage);
    }

    /**
     * Find a campaign by UUID with full eager loading.
     */
    public function findByUuid(string $uuid): ?Campaign
    {
        return Campaign::query()
            ->with('audience', 'whatsappLine', 'template', 'creator')
            ->where('uuid', $uuid)
            ->first();
    }
}
