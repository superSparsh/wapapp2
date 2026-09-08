<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Domains\AiBot\Services\Providers\AiProviderInterface;
use App\Enums\EmbeddingStatus;
use App\Models\AiBot;
use App\Models\AiBusinessInfo;
use Illuminate\Support\Facades\Cache;

class AiEmbeddingService
{
    public function __construct(
        private readonly AiProviderKeyService $providerKeyService,
        private readonly AiTokenUsageService $tokenUsageService,
    ) {}

    /**
     * Generate an embedding vector for the given text.
     *
     * @return array{embedding: array<float>, tokens: int}
     */
    public function generate(AiBot $bot, string $text): array
    {
        $config = $bot->resolveProvider();

        if (empty($config['api_key'])) {
            throw new \RuntimeException('No API key configured for AI provider.');
        }

        $provider = $this->providerKeyService->resolveProvider($bot->provider);

        $result = $provider->embed($text, [
            'api_key' => $config['api_key'],
            'model' => $config['embedding_model'],
        ]);

        $this->tokenUsageService->log([
            'ai_bot_id' => $bot->id,
            'provider' => $config['provider'],
            'model' => $config['embedding_model'],
            'request_type' => 'embedding',
            'embedding_tokens' => $result['tokens'],
            'total_tokens' => $result['tokens'],
        ]);

        return $result;
    }

    /**
     * Process a pending business info embedding.
     */
    public function processEmbedding(AiBusinessInfo $info, AiBot $bot): void
    {
        try {
            $text = $info->content ?: ($info->title ?? '');

            if (blank($text)) {
                $info->markEmbeddingFailed();
                return;
            }

            $result = $this->generate($bot, $text);

            // Store embedding ID (in a real system, you'd store the vector in a vector DB)
            $embeddingId = 'emb_' . md5($text . now()->timestamp);
            $info->markEmbeddingCompleted($embeddingId);

            // Cache the embedding for retrieval
            Cache::put(
                "ai_embedding:{$embeddingId}",
                $result['embedding'],
                now()->addDays(30),
            );
        } catch (\Throwable $e) {
            report($e);
            $info->markEmbeddingFailed();
        }
    }

    /**
     * Search for relevant context using cosine similarity on cached embeddings.
     *
     * @param  array<float>  $queryEmbedding
     * @return array<int, array{id: int, title: string, content: string, score: float}>
     */
    public function searchSimilar(AiBot $bot, array $queryEmbedding, int $topK = 3): array
    {
        $entries = AiBusinessInfo::query()
            ->where('ai_bot_id', $bot->id)
            ->where('embedding_status', EmbeddingStatus::Completed)
            ->whereNotNull('embedding_id')
            ->get();

        $results = [];

        foreach ($entries as $entry) {
            $cached = Cache::get("ai_embedding:{$entry->embedding_id}");

            if (! is_array($cached)) {
                continue;
            }

            $score = $this->cosineSimilarity($queryEmbedding, $cached);

            $results[] = [
                'id' => $entry->id,
                'title' => $entry->title,
                'content' => $entry->content,
                'score' => $score,
            ];
        }

        // Sort by score descending
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $topK);
    }

    /**
     * Calculate cosine similarity between two vectors.
     *
     * @param  array<float>  $a
     * @param  array<float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || empty($a)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0, $len = count($a); $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denominator = sqrt($normA) * sqrt($normB);

        return $denominator > 0 ? $dotProduct / $denominator : 0.0;
    }
}
