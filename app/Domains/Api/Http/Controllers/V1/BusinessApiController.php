<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Integration\Services\LineProfileService;
use App\Http\Controllers\Controller;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;

class BusinessApiController extends Controller
{
    public function detailsAndPhones(LineProfileService $lineProfiles): JsonResponse
    {
        $business = $lineProfiles->businessDetails();
        $lines = $lineProfiles->lines()->map(function (WhatsappLine $line): array {
            return [
                'uid' => $line->uuid,
                'phone' => PhoneNormalizer::normalize((string) $line->phone) ?? (string) $line->phone,
                'display_name' => $line->display_name,
                'is_default' => (bool) $line->is_default,
                'status' => $line->status?->value ?? (string) $line->status,
                'quality_rating' => $line->quality_rating,
                'messaging_limit_tier' => $line->messaging_limit_tier,
                'waba_id' => $line->waba_id,
                'connected' => method_exists($line, 'isConnected') ? $line->isConnected() : filled($line->alibaba_cust_space_id),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'business_name' => $business['business_name'] ?? null,
                'waba_id' => $business['waba_id'] ?? null,
                'verified' => (bool) ($business['verified'] ?? false),
                'quality_rating' => $business['quality_rating'] ?? null,
                'messaging_limit_tier' => $business['messaging_limit_tier'] ?? null,
                'profile' => $business['profile'] ?? [],
                'phones' => $lines,
            ],
        ]);
    }
}
