<?php

declare(strict_types=1);

namespace App\Domains\Drip\Http\Controllers;

use App\Domains\Drip\Http\Requests\SaveDripFlowDataRequest;
use App\Domains\Drip\Services\DripFlowService;
use App\Http\Controllers\Controller;
use App\Models\DripCampaign;
use Illuminate\Http\JsonResponse;

class DripFlowBuilderController extends Controller
{
    public function __construct(
        private readonly DripFlowService $flowService,
    ) {}

    public function getData(DripCampaign $campaign): JsonResponse
    {
        $data = $campaign->exported_data;

        return response()->json([
            'success' => true,
            'data' => $data ?? ['nodes' => [], 'edges' => []],
            'campaign' => [
                'id' => $campaign->id,
                'uuid' => $campaign->uuid,
                'name' => $campaign->name,
                'status' => $campaign->status->value,
                'node_count' => $campaign->nodeCount(),
            ],
        ]);
    }

    public function saveData(SaveDripFlowDataRequest $request, DripCampaign $campaign): JsonResponse
    {
        $validated = $request->validated();

        $this->flowService->saveFlowData($campaign, [
            'nodes' => $validated['nodes'],
            'edges' => $validated['edges'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Flow saved successfully.',
            'node_count' => $campaign->fresh()->nodeCount(),
        ]);
    }
}
