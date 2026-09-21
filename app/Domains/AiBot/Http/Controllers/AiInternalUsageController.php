<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Http\Controllers;

use App\Domains\AiBot\Services\AiTokenUsageService;
use App\Http\Controllers\Controller;
use App\Models\AiBot;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Internal callback from the Python AI service to persist token usage.
 */
class AiInternalUsageController extends Controller
{
    public function __construct(
        private readonly AiTokenUsageService $tokenUsage,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('ai.internal_secret', '');
        $provided = (string) $request->input('internal_secret', $request->header('X-AI-Internal-Secret', ''));

        if ($secret === '' || ! hash_equals($secret, $provided)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $customerId = $request->input('customer_id');
        $botUuid = $request->input('bot_id');

        $tenant = $this->resolveTenant($customerId);
        if ($tenant === null) {
            Log::warning('AI internal usage: tenant not found', ['customer_id' => $customerId]);

            return response()->json(['ok' => false, 'error' => 'tenant_not_found'], 404);
        }

        return $tenant->run(function () use ($request, $botUuid): JsonResponse {
            $botId = null;
            if (is_string($botUuid) && $botUuid !== '') {
                $botId = AiBot::query()->where('uuid', $botUuid)->value('id');
            }

            $prompt = (int) $request->input('prompt_tokens', 0);
            $completion = (int) $request->input('completion_tokens', 0);
            $embedding = (int) $request->input('embedding_tokens', 0);
            $total = $prompt + $completion + $embedding;

            if ($total <= 0) {
                return response()->json(['ok' => true, 'skipped' => true]);
            }

            $this->tokenUsage->log([
                'ai_bot_id' => $botId,
                'provider' => (string) $request->input('provider', 'openai'),
                'model' => (string) $request->input('model', 'unknown'),
                'request_type' => (string) $request->input('request_type', 'chat'),
                'prompt_tokens' => $prompt,
                'completion_tokens' => $completion,
                'embedding_tokens' => $embedding,
                'total_tokens' => $total,
            ]);

            return response()->json(['ok' => true]);
        });
    }

    private function resolveTenant(mixed $customerId): ?Tenant
    {
        if ($customerId === null || $customerId === '') {
            return null;
        }

        $customerId = (string) $customerId;

        $byId = Tenant::query()->find($customerId);
        if ($byId !== null) {
            return $byId;
        }

        return Tenant::query()
            ->where('settings->legacy_customer_id', $customerId)
            ->orWhere('settings->legacy_customer_id', (int) $customerId)
            ->first();
    }
}
