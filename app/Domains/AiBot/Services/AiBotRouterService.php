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
        $lineId = $conversation->whatsapp_line_id !== null
            ? (int) $conversation->whatsapp_line_id
            : null;

        // 1. Sticky bot only if it still belongs to this WhatsApp number.
        if ($conversation->active_ai_bot_id !== null) {
            $bot = AiBot::query()->find($conversation->active_ai_bot_id);

            if ($bot !== null && $bot->isActive() && $this->botAllowedForLine($bot, $lineId)) {
                return $this->chatService->processMessage($conversation, $bot, $userMessage);
            }

            // Wrong-number / inactive sticky bot — clear and re-route.
            $conversation->forceFill(['active_ai_bot_id' => null])->save();
        }

        // 2. Load active bots for this WhatsApp number (legacy null-line bots included)
        $activeBots = $this->queryService->activeBots($lineId);

        if ($activeBots->isEmpty()) {
            return null;
        }

        // Prefer bots explicitly bound to this line over legacy account-wide bots.
        $lineBound = $lineId !== null
            ? $activeBots->where('whatsapp_line_id', $lineId)->values()
            : $activeBots;
        $candidateBots = $lineBound->isNotEmpty() ? $lineBound : $activeBots;

        // 3. If only 1 bot, use it directly
        if ($candidateBots->count() === 1) {
            $bot = $candidateBots->first();
            $this->assignBotToConversation($conversation, $bot);

            return $this->chatService->processMessage($conversation, $bot, $userMessage);
        }

        // 4. Try keyword-based routing
        $matchedBot = $this->matchByKeyword($candidateBots, $userMessage);

        if ($matchedBot !== null) {
            $this->assignBotToConversation($conversation, $matchedBot);

            return $this->chatService->processMessage($conversation, $matchedBot, $userMessage);
        }

        // 5. Fall back to default bot for this line
        $defaultBot = $candidateBots->firstWhere('is_default', true) ?? $candidateBots->first();

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

    /**
     * A bot may answer on a line when:
     * - conversation has no line yet, or
     * - bot is legacy (null line) AND this line has no dedicated bots, or
     * - bot.whatsapp_line_id matches the conversation line.
     *
     * Never allow a bot bound to another WhatsApp number.
     */
    private function botAllowedForLine(AiBot $bot, ?int $lineId): bool
    {
        $botLineId = $bot->whatsapp_line_id !== null ? (int) $bot->whatsapp_line_id : null;

        if ($lineId === null) {
            return true;
        }

        if ($botLineId === $lineId) {
            return true;
        }

        // Other-number bot: never.
        if ($botLineId !== null) {
            return false;
        }

        // Legacy null-line bot: only if this number has no dedicated bots yet.
        return ! AiBot::query()
            ->active()
            ->where('whatsapp_line_id', $lineId)
            ->exists();
    }
}
