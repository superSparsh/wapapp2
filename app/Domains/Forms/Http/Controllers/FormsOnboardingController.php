<?php

declare(strict_types=1);

namespace App\Domains\Forms\Http\Controllers;

use App\Domains\Forms\Services\FormsOnboardingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class FormsOnboardingController extends Controller
{
    public function __construct(
        private readonly FormsOnboardingService $onboarding,
    ) {}

    /**
     * Legacy-compatible endpoint for forms.tekprocloud.com.
     * Route: POST /api/forms/onboarding
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $data = $this->onboarding->process($request);

            return response()->json([
                'success' => true,
                'message' => 'Minimal readiness API flow completed successfully.',
                'data' => $data,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Forms onboarding API failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
