<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Billing\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SubscriptionApiController extends Controller
{
    public function show(SubscriptionService $subscriptions): JsonResponse
    {
        $summary = $subscriptions->subscriptionSummary();
        $expiresAt = $summary['expires_at'] ?? null;

        return response()->json([
            'success' => true,
            'data' => [
                'plan_name' => $summary['plan_name'] ?? $summary['plan']?->name,
                'plan_id' => $summary['plan']?->id,
                'due_date' => $expiresAt?->toDateString(),
                'expires_at' => $expiresAt?->toIso8601String(),
                'is_cancelled' => (bool) ($summary['is_cancelled'] ?? false),
                'subscription_status' => $summary['subscription']?->status?->value
                    ?? (string) ($summary['subscription']?->status ?? null),
            ],
        ]);
    }
}
