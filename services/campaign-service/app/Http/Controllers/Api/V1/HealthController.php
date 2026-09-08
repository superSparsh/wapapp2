<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dbStatus = 'healthy';

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = 'unhealthy: ' . $e->getMessage();
        }

        return response()->json([
            'status' => 'healthy',
            'service' => 'campaign-service',
            'timestamp' => now()->toIso8601String(),
            'database' => $dbStatus,
        ]);
    }
}
