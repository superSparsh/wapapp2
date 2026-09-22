<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Repositories\Interfaces\CampaignRecipientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignStatsService
{
    public function __construct(
        private readonly CampaignRecipientRepositoryInterface $recipientRepo,
    ) {}

    /**
     * Gauge metrics: total, sent, delivered, failed, read, response, unsubscribed with percentages.
     *
     * @return array{total: int, pending: int, sent: int, delivered: int, failed: int, read: int, response: int, unsubscribed: int, delivered_pct: string, failed_pct: string, read_pct: string, response_pct: string, unsubscribed_pct: string}
     */
    public function gaugeMetrics(Campaign $campaign): array
    {
        $stats = $this->recipientRepo->getAggregateStats($campaign);

        $total = $stats['total'];
        $delivered = $stats['delivered'];
        $failed = $stats['failed'];
        $read = $stats['read'];
        $response = $stats['response'];
        $unsubscribed = $stats['unsubscribed'];

        $pct = fn (int $val): string => $total > 0 ? number_format(($val / $total) * 100) . '%' : '0%';

        return array_merge($stats, [
            'delivered_pct' => $pct($delivered),
            'failed_pct' => $pct($failed),
            'read_pct' => $pct($read),
            'response_pct' => $pct($response),
            'unsubscribed_pct' => $pct($unsubscribed),
        ]);
    }

    public function recipientLog(Campaign $campaign, int $perPage = 10, ?string $status = null): LengthAwarePaginator
    {
        return $this->recipientRepo->paginateForCampaign($campaign, $perPage, $status);
    }

    public function exportCsv(Campaign $campaign, ?string $status = null): StreamedResponse
    {
        $filename = 'campaign-recipients-' . $campaign->id . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($campaign, $status): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'SI. No',
                'Contact Phone',
                'Contact Name',
                'Status',
                'Reason',
                'Sent At',
                'Delivered At',
                'Failed At',
                'Read At',
            ]);

            $query = CampaignRecipient::query()
                ->where('campaign_id', $campaign->id)
                ->with('contact:id,name,phone')
                ->orderByDesc('created_at');

            if ($status !== null && $status !== '') {
                $query->where('status', $status);
            }

            $seq = 0;
            $query->cursor()->each(function (CampaignRecipient $recipient) use ($handle, &$seq): void {
                $statusValue = is_object($recipient->status) ? $recipient->status->value : (string) $recipient->status;
                $statusLabel = is_object($recipient->status) && method_exists($recipient->status, 'label')
                    ? $recipient->status->label()
                    : (string) $recipient->status;

                fputcsv($handle, [
                    ++$seq,
                    $recipient->contact_phone ?? 'N/A',
                    $recipient->contact?->name ?? 'N/A',
                    $statusLabel,
                    $statusValue === 'failed'
                        ? ($recipient->failure_reason ?: 'N/A')
                        : '—',
                    $recipient->sent_at?->format('d M Y h:i:s A') ?? 'N/A',
                    $recipient->delivered_at?->format('d M Y h:i:s A') ?? 'N/A',
                    $recipient->failed_at?->format('d M Y h:i:s A') ?? 'N/A',
                    $recipient->read_at?->format('d M Y h:i:s A') ?? 'N/A',
                ]);
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
