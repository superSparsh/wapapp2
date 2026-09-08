<?php

declare(strict_types=1);

namespace App\Domains\Drip\Services;

use App\Enums\ChatbotFlowStatAction;
use App\Enums\ChatbotFlowStateStatus;
use App\Models\DripCampaign;
use App\Models\DripCampaignStat;
use App\Models\DripCampaignState;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DripAudiencePresenter
{
    /**
     * Audience stats grid: contacts in action, done, skipped/pending, errors.
     *
     * @return array{in_action: int, done: int, pending: int, errors: int}
     */
    public function statsGrid(DripCampaign $campaign): array
    {
        $states = DripCampaignState::query()
            ->where('drip_campaign_id', $campaign->id)
            ->selectRaw("
                COUNT(CASE WHEN status = ? THEN 1 END) as active,
                COUNT(CASE WHEN status = ? THEN 1 END) as waiting,
                COUNT(CASE WHEN status = ? THEN 1 END) as completed,
                COUNT(CASE WHEN status = ? THEN 1 END) as expired
            ", [
                ChatbotFlowStateStatus::Active->value,
                ChatbotFlowStateStatus::Waiting->value,
                ChatbotFlowStateStatus::Completed->value,
                ChatbotFlowStateStatus::Expired->value,
            ])
            ->first();

        $errors = DripCampaignStat::query()
            ->where('drip_campaign_id', $campaign->id)
            ->where('action', ChatbotFlowStatAction::Error)
            ->distinct('contact_phone')
            ->count('contact_phone');

        return [
            'in_action' => (int) ($states->active ?? 0) + (int) ($states->waiting ?? 0),
            'done' => (int) ($states->completed ?? 0),
            'pending' => (int) ($states->waiting ?? 0) + (int) ($states->expired ?? 0),
            'errors' => $errors,
        ];
    }

    /**
     * Paginated list of unique contacts with their state status.
     *
     * @return array{contacts: LengthAwarePaginator, total: int}
     */
    public function contacts(DripCampaign $campaign, int $perPage = 10, ?string $search = null): array
    {
        $query = DripCampaignStat::query()
            ->where('drip_campaign_id', $campaign->id)
            ->whereNotNull('contact_phone')
            ->select('contact_phone')
            ->selectRaw('MAX(created_at) as last_activity')
            ->selectRaw('COUNT(*) as stat_count')
            ->groupBy('contact_phone');

        if ($search !== null && $search !== '') {
            $query->where('contact_phone', 'like', "%{$search}%");
        }

        $paginator = $query->orderByDesc('last_activity')->paginate($perPage);

        // Enrich each contact with state info
        $paginator->getCollection()->transform(function ($row) use ($campaign) {
            $state = DripCampaignState::query()
                ->where('drip_campaign_id', $campaign->id)
                ->whereHas('conversation', fn ($q) => $q->where('contact_phone', $row->contact_phone))
                ->latest()
                ->first();

            $row->state_status = $state?->status->value ?? 'updated';
            $row->updated_at = $row->last_activity;

            return $row;
        });

        $totalQuery = DripCampaignStat::query()
            ->where('drip_campaign_id', $campaign->id)
            ->whereNotNull('contact_phone');

        if ($search !== null && $search !== '') {
            $totalQuery->where('contact_phone', 'like', "%{$search}%");
        }

        $totalContacts = $totalQuery->distinct('contact_phone')->count('contact_phone');

        return [
            'contacts' => $paginator,
            'total' => $totalContacts,
        ];
    }

    /**
     * Timeline: recent activity feed from stats ordered by created_at desc.
     */
    public function timeline(DripCampaign $campaign, int $perPage = 15): LengthAwarePaginator
    {
        return DripCampaignStat::query()
            ->where('drip_campaign_id', $campaign->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
