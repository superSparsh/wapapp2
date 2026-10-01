<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AiSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy mobile provider-keys + usage analytics endpoints.
 * Backed by AiSetting where a dedicated provider-keys table is not present.
 */
class ProviderKeysController extends Controller
{
    public function getModels(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => 'gpt-4o-mini', 'label' => 'GPT-4o mini'],
                ['id' => 'gpt-4o', 'label' => 'GPT-4o'],
                ['id' => 'gpt-4.1', 'label' => 'GPT-4.1'],
            ],
        ]);
    }

    public function validateKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'api_key' => ['required', 'string', 'min:10'],
            'provider' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => str_starts_with($validated['api_key'], 'sk-') || strlen($validated['api_key']) > 20,
                'provider' => $validated['provider'] ?? 'openai',
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        $key = (string) AiSetting::get('openai_api_key', '');

        return response()->json([
            'success' => true,
            'data' => $key === '' ? [] : [[
                'id' => 1,
                'provider' => 'openai',
                'masked' => str_repeat('*', max(0, strlen($key) - 4)).substr($key, -4),
                'configured' => true,
            ]],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'api_key' => ['required', 'string', 'min:10', 'max:512'],
            'provider' => ['nullable', 'string', 'max:64'],
        ]);

        AiSetting::set('openai_api_key', $validated['api_key']);
        if (filled($validated['provider'] ?? null)) {
            AiSetting::set('ai_provider', $validated['provider']);
        }

        return response()->json(['success' => true, 'message' => 'Provider key saved'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->store($request);
    }

    public function destroy(int $id): JsonResponse
    {
        AiSetting::set('openai_api_key', '');

        return response()->json(['success' => true, 'message' => 'Provider key removed']);
    }

    public function usageSummary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_tokens' => 0,
                'total_requests' => 0,
            ],
        ]);
    }

    public function usageTimeline(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [],
        ]);
    }
}
