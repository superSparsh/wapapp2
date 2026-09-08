<?php

declare(strict_types=1);

namespace App\Domains\Drip\Http\Controllers;

use App\Domains\Drip\Services\DripInsightPresenter;
use App\Http\Controllers\Controller;
use App\Models\DripCampaign;
use Illuminate\View\View;

class DripInsightsController extends Controller
{
    public function __construct(
        private readonly DripInsightPresenter $insightPresenter,
    ) {}

    /**
     * Insights page with overview stats and per-step performance.
     */
    public function show(DripCampaign $campaign): View
    {
        return view('automation.drip-insights', [
            'campaign' => $campaign,
            'overview' => $this->insightPresenter->overviewStats($campaign),
            'steps' => $this->insightPresenter->performanceSteps($campaign),
        ]);
    }
}
