<?php

declare(strict_types=1);

namespace App\Domains\Drip\Http\Controllers;

use App\Domains\Drip\Services\DripCampaignStatService;
use App\Domains\Drip\Services\DripStatPresenter;
use App\Http\Controllers\Controller;
use App\Models\DripCampaign;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DripStatisticsController extends Controller
{
    public function __construct(
        private readonly DripStatPresenter $statPresenter,
        private readonly DripCampaignStatService $statService,
    ) {}

    /**
     * Statistics overview with gauge metrics.
     */
    public function overview(DripCampaign $campaign): View
    {
        return view('automation.drip-statistics', [
            'campaign' => $campaign,
            'metrics' => $this->statPresenter->gaugeMetrics($campaign),
            'aggregateStats' => $this->statService->aggregate($campaign->id),
        ]);
    }

    /**
     * Detailed message log with pagination.
     */
    public function detail(Request $request, DripCampaign $campaign): View
    {
        $status = $request->query('status');

        return view('automation.drip-statistics-detail', [
            'campaign' => $campaign,
            'messages' => $this->statPresenter->detailLog(
                $campaign,
                status: is_string($status) ? $status : null,
            ),
            'currentStatus' => is_string($status) ? $status : '',
        ]);
    }

    /**
     * Export statistics as CSV.
     */
    public function export(Request $request, DripCampaign $campaign): StreamedResponse
    {
        $status = $request->query('status');

        return $this->statPresenter->exportCsv(
            $campaign,
            is_string($status) ? $status : null,
        );
    }
}
