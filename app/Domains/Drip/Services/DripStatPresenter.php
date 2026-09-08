<?php

declare(strict_types=1);

namespace App\Domains\Drip\Services;

use App\Enums\ChatbotFlowStatAction;
use App\Models\DripCampaign;
use App\Models\DripCampaignStat;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DripStatPresenter
{
    /**
     * Gauge metrics: sent / delivered / read / failed with percentages.
     *
     * @return array{total: int, sent: int, delivered: int, read: int, failed: int, sent_pct: string, delivered_pct: string, read_pct: string, failed_pct: string}
     */
    public function gaugeMetrics(DripCampaign $campaign): array
    {
        $stats = DripCampaignStat::query()
            ->where('drip_campaign_id', $campaign->id)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN action = ? THEN 1 END) as entered,
                COUNT(CASE WHEN action = ? THEN 1 END) as completed,
                COUNT(CASE WHEN action = ? THEN 1 END) as dropped,
                COUNT(CASE WHEN action = ? THEN 1 END) as errors
            ", [
                ChatbotFlowStatAction::Entered->value,
                ChatbotFlowStatAction::Completed->value,
                ChatbotFlowStatAction::Dropped->value,
                ChatbotFlowStatAction::Error->value,
            ])
            ->first();

        $total = (int) ($stats->total ?? 0);
        $entered = (int) ($stats->entered ?? 0);
        $completed = (int) ($stats->completed ?? 0);
        $dropped = (int) ($stats->dropped ?? 0);
        $errors = (int) ($stats->errors ?? 0);

        // Sent = entered (messages attempted)
        // Delivered = completed (messages that reached the end)
        // Read = completed (best proxy we have in WhatsApp context)
        // Failed = errors
        $pct = fn (int $val): string => $total > 0 ? number_format(($val / $total) * 100) . '%' : '0%';

        return [
            'total' => $total,
            'sent' => $entered,
            'delivered' => $completed,
            'read' => $completed,
            'failed' => $errors,
            'dropped' => $dropped,
            'sent_pct' => $pct($entered),
            'delivered_pct' => $pct($completed),
            'read_pct' => $pct($completed),
            'failed_pct' => $pct($errors),
        ];
    }

    /**
     * Paginated detail log of individual stat records.
     */
    public function detailLog(DripCampaign $campaign, int $perPage = 10): LengthAwarePaginator
    {
        return DripCampaignStat::query()
            ->where('drip_campaign_id', $campaign->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Stream CSV export of all stat records for this campaign.
     */
    public function exportCsv(DripCampaign $campaign): StreamedResponse
    {
        $filename = 'drip-stats-' . $campaign->id . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($campaign): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SI. No', 'Contact Phone', 'Node ID', 'Node Type', 'Action', 'Sent At']);

            $seq = 0;
            DripCampaignStat::query()
                ->where('drip_campaign_id', $campaign->id)
                ->orderByDesc('created_at')
                ->cursor()
                ->each(function (DripCampaignStat $stat) use ($handle, &$seq): void {
                    fputcsv($handle, [
                        ++$seq,
                        $stat->contact_phone ?? 'N/A',
                        $stat->node_id ?? 'N/A',
                        $stat->node_type ?? 'N/A',
                        $stat->action->value,
                        $stat->created_at->format('d M Y h:i:s A'),
                    ]);
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
