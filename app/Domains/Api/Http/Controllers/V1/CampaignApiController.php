<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Campaigns\Services\CampaignQueryService;
use App\Http\Controllers\Controller;
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
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'list_uid' => ['nullable', 'string'],
            'template_uid' => ['nullable', 'string'],
            'phone_number' => ['nullable', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Campaign creation stub accepted.',
            'payload' => $validated,
        ], 202);
    }
}
