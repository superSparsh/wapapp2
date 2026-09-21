<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Models\AiBot;
use Illuminate\Support\Facades\Log;

/**
 * Optional local fallback only. Primary KB retrieval is Chroma via KnowledgeBaseProxyService / process_query.
 * Do not treat MySQL ai_business_info as the Knowledge Base source of truth.
 */
class AiRagService
{
    public function __construct(
        private readonly AiEmbeddingService $embeddingService,
        private readonly AiPythonClient $pythonClient,
    ) {}

    /**
     * Retrieve relevant context from the knowledge base for the given query.
     * Prefer empty string when AI service handles RAG via process_query.
     */
    public function retrieveContext(AiBot $bot, string $userQuery): string
    {
        if ($this->pythonClient->isConfigured()) {
            // Chat path should use process_query; skip MySQL embedding mirror.
            return '';
        }

        $entryCount = $bot->businessInfoEntries()
            ->where('embedding_status', 'completed')
            ->count();

        if ($entryCount === 0) {
            return '';
        }

        try {
            $queryEmbedding = $this->embeddingService->generate($bot, $userQuery);
            $results = $this->embeddingService->searchSimilar(
                $bot,
                $queryEmbedding['embedding'],
                topK: 3,
            );

            if (empty($results)) {
                return '';
            }

            $contextParts = [];
            foreach ($results as $result) {
                if ($result['score'] < 0.5) {
                    continue;
                }

                $content = $result['content'] ?: $result['title'];
                if (blank($content)) {
                    continue;
                }

                $contextParts[] = "[{$result['title']}]: {$content}";
            }

            if (empty($contextParts)) {
                return '';
            }

            return "Relevant business knowledge:\n".implode("\n\n", $contextParts);
        } catch (\Throwable $e) {
            Log::warning('RAG context retrieval failed', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }
}
