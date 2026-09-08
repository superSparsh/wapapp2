<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignStatsService
{
    /**
     * Gauge metrics: total, sent, delivered, failed, read, response, unsubscribed with percentages.
     * Single aggregate query using conditional COUNT for optimal performance.
     *
     * @return array{total: int, sent: int, delivered: int, failed: int, read: int, response: int, unsubscribed: int, delivered_pct: string, failed_pct: string, read_pct: string, response_pct: string, unsubscribed_pct: string}
     */
    public function gaugeMetrics(Campaign $campaign): array
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

        $total = (int) ($stats->total ?? 0);

        if ($total === 0) {
            return $this->gaugeMetricsFromCampaignAggregates($campaign);
        }

        // Progressive statuses: read/response imply delivery for the Delivered gauge.
        $deliveredExclusive = (int) ($stats->delivered ?? 0);
        $failed = (int) ($stats->failed ?? 0);
        $read = (int) ($stats->read ?? 0);
        $response = (int) ($stats->response ?? 0);
        $unsubscribed = (int) ($stats->unsubscribed ?? 0);
        $delivered = $deliveredExclusive + $read + $response;

        $pct = fn (int $val): string => $total > 0 ? number_format(($val / $total) * 100).'%' : '0%';

        return [
            'total' => $total,
            'pending' => (int) ($stats->pending ?? 0),
            'sent' => (int) ($stats->sent ?? 0),
            'delivered' => $delivered,
            'failed' => $failed,
            'read' => $read,
            'response' => $response,
            'unsubscribed' => $unsubscribed,
            'delivered_pct' => $pct($delivered),
            'failed_pct' => $pct($failed),
            'read_pct' => $pct($read),
            'response_pct' => $pct($response),
            'unsubscribed_pct' => $pct($unsubscribed),
        ];
    }

    /**
     * @return array{total: int, pending: int, sent: int, delivered: int, failed: int, read: int, response: int, unsubscribed: int, delivered_pct: string, failed_pct: string, read_pct: string, response_pct: string, unsubscribed_pct: string}
     */
    private function gaugeMetricsFromCampaignAggregates(Campaign $campaign): array
    {
        $total = max(0, (int) $campaign->total_recipients);
        $delivered = max(0, (int) $campaign->total_delivered);
        $failed = max(0, (int) $campaign->total_failed);
        $read = max(0, (int) $campaign->total_read);
        $response = max(0, (int) $campaign->total_response);
        $unsubscribed = max(0, (int) $campaign->total_unsubscribed);
        $sent = max(0, $delivered + $failed);

        $pct = fn (int $val): string => $total > 0 ? number_format(($val / $total) * 100).'%' : '0%';

        return [
            'total' => $total,
            'pending' => max(0, $total - $sent),
            'sent' => $sent,
            'delivered' => $delivered,
            'failed' => $failed,
            'read' => $read,
            'response' => $response,
            'unsubscribed' => $unsubscribed,
            'delivered_pct' => $pct($delivered),
            'failed_pct' => $pct($failed),
            'read_pct' => $pct($read),
            'response_pct' => $pct($response),
            'unsubscribed_pct' => $pct($unsubscribed),
        ];
    }

    /**
     * Paginated recipient log with optional status filter.
     */
    public function recipientLog(Campaign $campaign, int $perPage = 10, ?string $status = null): LengthAwarePaginator
    {
        $query = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->with('contact:id,name,phone')
            ->orderByDesc('created_at');

        if ($status !== null && $status !== '') {
            $enum = CampaignRecipientStatus::tryFrom($status);
            if ($enum !== null) {
                $query->where('status', $enum);
            }
        }

        return $query->paginate($perPage);
    }

    /**
     * Stream CSV export of all recipients for this campaign.
     * Uses cursor() for memory-efficient streaming.
     */
    public function exportCsv(Campaign $campaign): StreamedResponse
    {
        $filename = 'campaign-recipients-' . $campaign->id . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($campaign): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SI. No', 'Contact Phone', 'Contact Name', 'Status', 'Sent At', 'Delivered At', 'Read At', 'Failed Reason']);

            $seq = 0;
            CampaignRecipient::query()
                ->where('campaign_id', $campaign->id)
                ->with('contact:id,name,phone')
                ->orderByDesc('created_at')
                ->cursor()
                ->each(function (CampaignRecipient $recipient) use ($handle, &$seq): void {
                    fputcsv($handle, [
                        ++$seq,
                        $recipient->contact_phone,
                        $recipient->contact?->name ?? 'N/A',
                        $recipient->status->label(),
                        $recipient->sent_at?->format('d M Y h:i:s A') ?? 'N/A',
                        $recipient->delivered_at?->format('d M Y h:i:s A') ?? 'N/A',
                        $recipient->read_at?->format('d M Y h:i:s A') ?? 'N/A',
                        $recipient->failure_reason ?? 'N/A',
                    ]);
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Aggregate dashboard stats across all campaigns.
     */
    public function dashboardStats(): array
    {
        $stats = Campaign::query()
            ->selectRaw('
                COUNT(*) as total_campaigns,
                SUM(total_recipients) as total_sent,
                SUM(total_delivered) as total_delivered,
                SUM(total_failed) as total_failed,
                SUM(total_read) as total_read
            ')
            ->first();

        return [
            'total_campaigns' => (int) ($stats->total_campaigns ?? 0),
            'total_sent' => (int) ($stats->total_sent ?? 0),
            'total_delivered' => (int) ($stats->total_delivered ?? 0),
            'total_failed' => (int) ($stats->total_failed ?? 0),
            'total_read' => (int) ($stats->total_read ?? 0),
        ];
    }
}
