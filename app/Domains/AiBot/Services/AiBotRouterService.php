<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Models\AiBot;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class AiBotRouterService
{
    public function __construct(
        private readonly AiBotQueryService $queryService,
        private readonly AiChatService $chatService,
    ) {}

    /**
     * Route an inbound message to the appropriate AI bot and process it.
     *
     * Strategy:
     * 1. If conversation already has an active_ai_bot_id, use that bot
     * 2. If 0-1 active bots exist, use default (skip classification)
     * 3. Try keyword-based routing first (free, instant)
     * 4. If no keyword match, use default bot
     */
    public function route(Conversation $conversation, string $userMessage): ?string
    {
        // 1. Check if conversation already has an active bot
        if ($conversation->active_ai_bot_id !== null) {
            $bot = AiBot::query()->find($conversation->active_ai_bot_id);

            if ($bot !== null && $bot->isActive()) {
                return $this->chatService->processMessage($conversation, $bot, $userMessage);
            }
        }

        // 2. Load active bots
        $activeBots = $this->queryService->activeBots();

        if ($activeBots->isEmpty()) {
            return null;
        }

        // 3. If only 1 bot, use it directly
        if ($activeBots->count() === 1) {
            $bot = $activeBots->first();
            $this->assignBotToConversation($conversation, $bot);

            return $this->chatService->processMessage($conversation, $bot, $userMessage);
        }

        // 4. Try keyword-based routing
        $matchedBot = $this->matchByKeyword($activeBots, $userMessage);

        if ($matchedBot !== null) {
            $this->assignBotToConversation($conversation, $matchedBot);

            return $this->chatService->processMessage($conversation, $matchedBot, $userMessage);
        }

        // 5. Fall back to default bot
        $defaultBot = $activeBots->firstWhere('is_default', true) ?? $activeBots->first();

        if ($defaultBot !== null) {
            $this->assignBotToConversation($conversation, $defaultBot);

            return $this->chatService->processMessage($conversation, $defaultBot, $userMessage);
        }

        return null;
    }

    /**
     * Match a bot based on keywords in the user message.
     * Checks bot name and type against message words.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, AiBot>  $bots
     */
    private function matchByKeyword($bots, string $message): ?AiBot
    {
        $messageLower = mb_strtolower($message);
        $words = preg_split('/\s+/', $messageLower) ?: [];

        foreach ($bots as $bot) {
            $botKeywords = $this->extractBotKeywords($bot);

            foreach ($botKeywords as $keyword) {
                if ($keyword === '') {
                    continue;
                }

                if (in_array($keyword, $words, true) || str_contains($messageLower, $keyword)) {
                    return $bot;
                }
            }
        }

        return null;
    }

    /**
     * Extract searchable keywords from a bot's name and type.
     *
     * @return array<string>
     */
    private function extractBotKeywords(AiBot $bot): array
    {
        $keywords = [];

        // Add lowercase name words
        $nameWords = preg_split('/[\s_-]+/', mb_strtolower($bot->name)) ?: [];
        $keywords = array_merge($keywords, $nameWords);

        // Add type if set
        if (! empty($bot->type)) {
            $typeWords = preg_split('/[\s_-]+/', mb_strtolower($bot->type)) ?: [];
            $keywords = array_merge($keywords, $typeWords);
        }

        return array_filter(array_unique($keywords), fn ($w) => mb_strlen($w) >= 3);
    }

    private function assignBotToConversation(Conversation $conversation, AiBot $bot): void
    {
        if ($conversation->active_ai_bot_id !== $bot->id) {
            $conversation->forceFill(['active_ai_bot_id' => $bot->id])->save();
        }
    }
}
