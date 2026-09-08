<?php

declare(strict_types=1);

namespace App\Domains\Drip\Services;

use App\Domains\Drip\Support\DripFlowCacheManager;
use App\Models\DripCampaign;

class DripFlowService
{
    public function __construct(
        private readonly DripFlowCacheManager $cacheManager,
    ) {}

    /**
     * @param  array{nodes: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $flowData
     */
    public function saveFlowData(DripCampaign $campaign, array $flowData): DripCampaign
    {
        $campaign->update([
            'exported_data' => [
                'nodes' => $flowData['nodes'],
                'edges' => $flowData['edges'] ?? [],
            ],
        ]);

        $this->cacheManager->forgetNodeMap($campaign->id);

        return $campaign->refresh();
    }
}
