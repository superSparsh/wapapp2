<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Models\AiBot;
use Illuminate\Support\Facades\Log;

class AiRagService
{
    public function __construct(
        private readonly AiEmbeddingService $embeddingService,
    ) {}

    /**
     * Retrieve relevant context from the knowledge base for the given query.
     *
     * @return string  Formatted context string to include in the prompt.
     */
    public function retrieveContext(AiBot $bot, string $userQuery): string
    {
        // Check if bot has any knowledge base entries
        $entryCount = $bot->businessInfoEntries()
            ->where('embedding_status', 'completed')
            ->count();

        if ($entryCount === 0) {
            return '';
        }

        try {
            // Generate embedding for the user query
            $queryEmbedding = $this->embeddingService->generate($bot, $userQuery);

            // Search for similar documents
            $results = $this->embeddingService->searchSimilar(
                $bot,
                $queryEmbedding['embedding'],
                topK: 3,
            );

            if (empty($results)) {
                return '';
            }

            // Format context from top results
            $contextParts = [];
            foreach ($results as $result) {
                if ($result['score'] < 0.5) {
                    continue; // Skip low-relevance results
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

            return "Relevant business knowledge:\n" . implode("\n\n", $contextParts);
        } catch (\Throwable $e) {
            Log::warning('RAG context retrieval failed', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }
}
