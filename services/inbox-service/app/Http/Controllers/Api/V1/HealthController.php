<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $dbStatus = 'ok';

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = 'error: '.$e->getMessage();
        }

        return response()->json([
            'status' => $dbStatus === 'ok' ? 'healthy' : 'unhealthy',
            'service' => 'inbox-service',
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
            'database' => $dbStatus,
        ], $dbStatus === 'ok' ? 200 : 503);
    }
}
