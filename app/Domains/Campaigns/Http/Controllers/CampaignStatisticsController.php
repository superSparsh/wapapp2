<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Http\Controllers;

use App\Domains\Campaigns\Services\CampaignServiceAdapter;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignStatisticsController extends Controller
{
    public function __construct(
        private readonly CampaignServiceAdapter $adapter,
    ) {}

    /**
     * Statistics overview with gauge metrics.
     */
    public function overview(Campaign $bulkCampaign): View
    {
        $bulkCampaign->load('audience', 'whatsappLine');

        return view('campaigns.statistics', [
            'campaign' => $bulkCampaign,
            'metrics' => $this->adapter->getGaugeMetrics($bulkCampaign),
        ]);
    }

    /**
     * Paginated recipient log with optional status filter.
     */
    public function detail(Request $request, Campaign $bulkCampaign): View
    {
        $bulkCampaign->load('audience');

        $recipients = $this->adapter->recipientLog(
            $bulkCampaign,
            perPage: (int) config('campaigns.per_page', 10),
            status: $request->query('status'),
        );

        return view('campaigns.detail', [
            'campaign' => $bulkCampaign,
            'recipients' => $recipients,
            'currentStatus' => $request->query('status', ''),
        ]);
    }

    /**
     * CSV export of all recipients.
     */
    public function export(Campaign $bulkCampaign): StreamedResponse
    {
        return $this->adapter->exportCsv($bulkCampaign);
    }
}
