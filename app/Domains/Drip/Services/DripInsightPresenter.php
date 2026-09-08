<?php

declare(strict_types=1);

namespace App\Domains\Drip\Services;

use App\Enums\ChatbotFlowStatAction;
use App\Models\DripCampaign;
use App\Models\DripCampaignStat;
use Illuminate\Support\Collection;

class DripInsightPresenter
{
    /**
     * Overview stats: total contacts, involved (entered), completion %.
     *
     * @return array{total_contacts: int, involved: int, completion_pct: string}
     */
    public function overviewStats(DripCampaign $campaign): array
    {
        $stats = DripCampaignStat::query()
            ->where('drip_campaign_id', $campaign->id)
            ->selectRaw("
                COUNT(DISTINCT contact_phone) as total_contacts,
                COUNT(CASE WHEN action = ? THEN 1 END) as involved,
                COUNT(CASE WHEN action = ? THEN 1 END) as completed
            ", [
                ChatbotFlowStatAction::Entered->value,
                ChatbotFlowStatAction::Completed->value,
            ])
            ->first();

        $involved = (int) ($stats->involved ?? 0);
        $completed = (int) ($stats->completed ?? 0);

        return [
            'total_contacts' => (int) ($stats->total_contacts ?? 0),
            'involved' => $involved,
            'completion_pct' => $involved > 0
                ? number_format(($completed / $involved) * 100, 2) . '%'
                : '0.00%',
        ];
    }

    /**
     * Per-step performance: each node with trigger count, completion rate, last updated.
     *
     * @return Collection<int, array{node_id: string, node_type: string, triggered: int, completed: int, rate: string, last_updated: string}>
     */
    public function performanceSteps(DripCampaign $campaign): Collection
    {
        $rows = DripCampaignStat::query()
            ->where('drip_campaign_id', $campaign->id)
            ->whereNotNull('node_id')
            ->select('node_id', 'node_type')
            ->selectRaw("
                COUNT(CASE WHEN action = ? THEN 1 END) as triggered,
                COUNT(CASE WHEN action = ? THEN 1 END) as completed,
                MAX(created_at) as last_updated
            ", [
                ChatbotFlowStatAction::Entered->value,
                ChatbotFlowStatAction::Completed->value,
            ])
            ->groupBy('node_id', 'node_type')
            ->orderByDesc('last_updated')
            ->get();

        return $rows->map(function ($row) {
            $triggered = (int) $row->triggered;
            $completed = (int) $row->completed;

            return [
                'node_id' => $row->node_id,
                'node_type' => $row->node_type ?? 'unknown',
                'triggered' => $triggered,
                'completed' => $completed,
                'rate' => $triggered > 0
                    ? number_format(($completed / $triggered) * 100, 2) . '%'
                    : '0.00%',
                'last_updated' => $row->last_updated
                    ? \Carbon\Carbon::parse($row->last_updated)->diffForHumans()
                    : 'N/A',
            ];
        });
    }
}
