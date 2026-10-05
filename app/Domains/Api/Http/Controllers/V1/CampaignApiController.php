<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Api\Services\PartnerCampaignService;
use App\Domains\Campaigns\Services\CampaignQueryService;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignApiController extends Controller
{
    public function index(Request $request, CampaignQueryService $queryService): JsonResponse
    {
        $paginator = $queryService->paginate(
            perPage: (int) $request->integer('per_page', 25),
            search: $request->query('search'),
            status: $request->query('status'),
            sort: $request->query('sort', 'created_at'),
            direction: $request->query('direction', 'desc'),
        );

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Campaign $c): array => $this->serialize($c))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(string $uid, CampaignQueryService $queryService): JsonResponse
    {
        $campaign = $queryService->findByUuid($uid);
        abort_if($campaign === null, 404, 'Campaign not found.');

        return response()->json([
            'success' => true,
            'data' => $this->serialize($campaign, detailed: true),
        ]);
    }

    public function store(Request $request, PartnerCampaignService $partnerCampaigns): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'list_uid' => ['required', 'string'],
            'template_uid' => ['required', 'string'],
            'phone_number' => ['nullable', 'string', 'max:32'],
            'schedule_datetime' => ['nullable', 'string', 'max:64'],
            'variables_array' => ['nullable'],
            'template_variables' => ['nullable'],
            'variables' => ['nullable'],
        ]);

        $campaign = $partnerCampaigns->createAndDispatch(array_merge($request->all(), $validated));

        return response()->json([
            'success' => true,
            'message' => $campaign->scheduled_at && $campaign->status?->value === 'scheduled'
                ? 'Campaign scheduled successfully.'
                : 'Campaign created and queued for sending.',
            'data' => $this->serialize($campaign->fresh(['audience', 'whatsappLine', 'template']) ?? $campaign, detailed: true),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Campaign $campaign, bool $detailed = false): array
    {
        $data = [
            'uid' => $campaign->uuid,
            'name' => $campaign->name,
            'status' => $campaign->status?->value ?? (string) $campaign->status,
            'list_uid' => $campaign->audience?->uuid,
            'template_uid' => $campaign->template?->uuid,
            'phone_number' => $campaign->whatsappLine?->phone,
            'scheduled_at' => optional($campaign->scheduled_at)?->toIso8601String(),
            'total_recipients' => (int) ($campaign->total_recipients ?? 0),
            'total_delivered' => (int) ($campaign->total_delivered ?? 0),
            'total_failed' => (int) ($campaign->total_failed ?? 0),
            'created_at' => optional($campaign->created_at)?->toIso8601String(),
        ];

        if ($detailed) {
            $data['total_read'] = (int) ($campaign->total_read ?? 0);
            $data['total_response'] = (int) ($campaign->total_response ?? 0);
            $data['template_name'] = $campaign->template?->name;
            $data['list_name'] = $campaign->audience?->name;
        }

        return $data;
    }
}
