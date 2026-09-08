<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Services\CampaignQueryService;
use App\Services\CampaignService;
use App\Support\CampaignPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaignService,
        private readonly CampaignQueryService $queryService,
        private readonly CampaignPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->queryService->paginate(
            perPage: (int) $request->integer('per_page', 10),
            search: $request->query('search'),
            status: $request->query('status'),
            sort: $request->query('sort', 'created_at'),
            direction: $request->query('direction', 'desc'),
        );

        $items = collect($paginator->items())->map(function (Campaign $campaign) {
            return array_merge($campaign->toArray(), [
                'card' => $this->presenter->indexCard($campaign),
            ]);
        });

        return response()->json([
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $campaign = $this->campaignService->create($request->validated());

        return response()->json([
            'campaign' => $campaign,
            'card' => $this->presenter->indexCard($campaign),
            'message' => 'Campaign created successfully.',
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        return response()->json([
            'campaign' => $campaign,
            'card' => $this->presenter->indexCard($campaign),
        ]);
    }

    public function update(UpdateCampaignRequest $request, string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $updated = $this->campaignService->update($campaign, $request->validated());

        return response()->json([
            'campaign' => $updated,
            'card' => $this->presenter->indexCard($updated),
            'message' => 'Campaign updated successfully.',
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campaign deleted successfully.',
        ]);
    }

    public function launch(string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $launched = $this->campaignService->launch($campaign);

        return response()->json([
            'campaign' => $launched,
            'message' => 'Campaign launched successfully.',
        ]);
    }

    public function toggle(string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $toggled = $this->campaignService->toggle($campaign);

        return response()->json([
            'campaign' => $toggled,
            'message' => $toggled->isSending() ? 'Campaign resumed.' : 'Campaign paused.',
        ]);
    }

    public function duplicate(string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $newCampaign = $this->campaignService->duplicate($campaign);

        return response()->json([
            'campaign' => $newCampaign,
            'message' => 'Campaign duplicated successfully.',
        ], 201);
    }
}
