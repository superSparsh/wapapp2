<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CampaignQueryService;
use App\Services\CampaignStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignStatsController extends Controller
{
    public function __construct(
        private readonly CampaignQueryService $queryService,
        private readonly CampaignStatsService $statsService,
    ) {}

    public function overview(string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        return response()->json([
            'campaign' => $campaign,
            'metrics' => $this->statsService->gaugeMetrics($campaign),
        ]);
    }

    public function recipientLog(Request $request, string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $paginator = $this->statsService->recipientLog(
            campaign: $campaign,
            perPage: (int) $request->integer('per_page', 10),
            status: $request->query('status'),
        );

        return response()->json([
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function export(string $uuid): StreamedResponse|JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        return $this->statsService->exportCsv($campaign);
    }
}
