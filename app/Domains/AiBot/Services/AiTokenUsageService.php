<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Models\AiTokenUsageLog;

class AiTokenUsageService
{
    /**
     * Log token usage for a request.
     *
     * @param  array<string, mixed>  $params
     */
    public function log(array $params): AiTokenUsageLog
    {
        return AiTokenUsageLog::query()->create([
            'ai_bot_id' => $params['ai_bot_id'] ?? null,
            'provider' => $params['provider'] ?? 'openai',
            'model' => $params['model'] ?? 'unknown',
            'request_type' => $params['request_type'] ?? 'chat',
            'prompt_tokens' => $params['prompt_tokens'] ?? 0,
            'completion_tokens' => $params['completion_tokens'] ?? 0,
            'embedding_tokens' => $params['embedding_tokens'] ?? 0,
            'total_tokens' => $params['total_tokens'] ?? 0,
            'estimated_cost_usd' => $this->estimateCost(
                $params['model'] ?? 'unknown',
                $params['prompt_tokens'] ?? 0,
                $params['completion_tokens'] ?? 0,
                $params['embedding_tokens'] ?? 0,
            ),
            'conversation_id' => $params['conversation_id'] ?? null,
        ]);
    }

    /**
     * Get total usage stats for a bot.
     *
     * @return array{total_tokens: int, total_cost: string, request_count: int}
     */
    public function botStats(int $botId): array
    {
        $stats = AiTokenUsageLog::query()
            ->where('ai_bot_id', $botId)
            ->selectRaw('SUM(total_tokens) as total_tokens, SUM(estimated_cost_usd) as total_cost, COUNT(*) as request_count')
            ->first();

        return [
            'total_tokens' => (int) ($stats?->total_tokens ?? 0),
            'total_cost' => number_format((float) ($stats?->total_cost ?? 0), 4),
            'request_count' => (int) ($stats?->request_count ?? 0),
        ];
    }

    /**
     * Get global usage stats across all bots (single-query aggregation).
     *
     * @return array{total_requests: int, total_tokens: int, prompt_tokens: int, completion_tokens: int, embedding_tokens: int, total_cost_usd: float, by_model: array<int, array<string, mixed>>}
     */
    public function globalStats(): array
    {
        $result = AiTokenUsageLog::query()
            ->selectRaw("
                COUNT(*) as total_requests,
                SUM(prompt_tokens) as prompt_tokens,
                SUM(completion_tokens) as completion_tokens,
                SUM(embedding_tokens) as embedding_tokens,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost_usd) as total_cost
            ")
            ->first();

        $byModel = AiTokenUsageLog::query()
            ->selectRaw("model, COUNT(*) as requests, SUM(prompt_tokens) as prompt_tokens, SUM(completion_tokens) as completion_tokens, SUM(total_tokens) as total_tokens, SUM(estimated_cost_usd) as cost_usd")
            ->groupBy('model')
            ->orderByDesc('total_tokens')
            ->get()
            ->map(fn ($row) => [
                'model' => $row->model,
                'requests' => (int) $row->requests,
                'prompt_tokens' => (int) $row->prompt_tokens,
                'completion_tokens' => (int) $row->completion_tokens,
                'total_tokens' => (int) $row->total_tokens,
                'cost_usd' => (float) $row->cost_usd,
            ])
            ->all();

        return [
            'total_requests' => (int) ($result?->total_requests ?? 0),
            'total_tokens' => (int) ($result?->total_tokens ?? 0),
            'prompt_tokens' => (int) ($result?->prompt_tokens ?? 0),
            'completion_tokens' => (int) ($result?->completion_tokens ?? 0),
            'embedding_tokens' => (int) ($result?->embedding_tokens ?? 0),
            'total_cost_usd' => (float) ($result?->total_cost ?? 0),
            'by_model' => $byModel,
        ];
    }

    /**
     * Estimate cost based on model pricing (approximate USD per 1M tokens).
     */
    private function estimateCost(string $model, int $promptTokens, int $completionTokens, int $embeddingTokens): float
    {
        // Pricing per 1M tokens (approximate, as of 2024)
        $pricing = [
            'gpt-4o' => ['prompt' => 5.0, 'completion' => 15.0],
            'gpt-4o-mini' => ['prompt' => 0.15, 'completion' => 0.60],
            'gpt-4-turbo' => ['prompt' => 10.0, 'completion' => 30.0],
            'gpt-3.5-turbo' => ['prompt' => 0.50, 'completion' => 1.50],
            'text-embedding-3-small' => ['embedding' => 0.02],
            'text-embedding-3-large' => ['embedding' => 0.13],
            'text-embedding-ada-002' => ['embedding' => 0.10],
            'gemini-1.5-flash' => ['prompt' => 0.075, 'completion' => 0.30],
            'gemini-1.5-pro' => ['prompt' => 1.25, 'completion' => 5.0],
        ];

        $rates = $pricing[$model] ?? ['prompt' => 1.0, 'completion' => 2.0, 'embedding' => 0.10];

        $cost = 0.0;
        $cost += ($promptTokens / 1_000_000) * ($rates['prompt'] ?? 1.0);
        $cost += ($completionTokens / 1_000_000) * ($rates['completion'] ?? 2.0);
        $cost += ($embeddingTokens / 1_000_000) * ($rates['embedding'] ?? 0.10);

        return round($cost, 8);
    }
}
