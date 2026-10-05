<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Api\Services\ApiLoginTokenService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginTokenApiController extends Controller
{
    public function store(Request $request, ApiLoginTokenService $tokens): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $tenant = tenant();
        if (! $tenant instanceof Tenant) {
            return response()->json(['message' => 'Tenant context missing.'], 422);
        }

        $issued = $tokens->issue($user, $tenant);

        return response()->json([
            'success' => true,
            'token' => $issued['token'],
            'url' => $issued['url'],
            'expires_in' => $issued['expires_in'],
        ]);
    }
}
