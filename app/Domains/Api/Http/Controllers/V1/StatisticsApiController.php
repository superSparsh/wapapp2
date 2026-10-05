<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Dashboard\Services\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatisticsApiController extends Controller
{
    public function businessConversations(Request $request, DashboardService $dashboard): JsonResponse
    {
        $credits = $dashboard->creditsPayload($request->query('period'));

        return response()->json([
            'success' => true,
            'data' => [
                'marketing' => (int) ($credits['marketing'] ?? 0),
                'marketing_limit' => $credits['marketing_limit'] ?? null,
                'utility' => (int) ($credits['utility'] ?? 0),
                'utility_limit' => $credits['utility_limit'] ?? null,
                'authentication' => (int) ($credits['authentication'] ?? 0),
                'sent' => (int) ($credits['sent'] ?? (($credits['marketing'] ?? 0) + ($credits['utility'] ?? 0))),
                'period' => $credits['period'] ?? $request->query('period'),
            ],
        ]);
    }

    public function serviceConversations(Request $request, DashboardService $dashboard): JsonResponse
    {
        $credits = $dashboard->creditsPayload($request->query('period'));

        return response()->json([
            'success' => true,
            'data' => [
                'service' => (int) ($credits['service'] ?? 0),
                'service_limit' => $credits['service_limit'] ?? null,
                'service_free_used' => (int) ($credits['service_free_used'] ?? 0),
                'service_free_remaining' => $credits['service_free_remaining'] ?? null,
                'service_free_per_number' => $credits['service_free_per_number'] ?? null,
                'period' => $credits['period'] ?? $request->query('period'),
            ],
        ]);
    }
}
