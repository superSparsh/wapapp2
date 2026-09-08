<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\AiBot\Services\AiProviderKeyService;
use App\Models\AiProviderKey;

class AITemplateSuggestionService
{
    public function __construct(
        private readonly AiProviderKeyService $providerKeyService,
    ) {}

    /**
     * @return list<string>
     */
    public function suggestBodies(string $prompt, ?string $context = null): array
    {
        $key = AiProviderKey::query()->active()->orderByDesc('id')->first();
        if (! $key instanceof AiProviderKey) {
            throw new \RuntimeException('No active AI provider key configured. Add one in OpenAI Key settings.');
        }

        $provider = $this->providerKeyService->resolveProvider($key->provider);
        $userMessage = trim($context ?? '') !== ''
            ? "Current draft:\n{$context}\n\nRequest: {$prompt}"
            : $prompt;

        $result = $provider->chat([
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $userMessage],
        ], [
            'api_key' => $key->api_key,
            'model' => $key->chat_model ?? 'gpt-4o-mini',
            'temperature' => 0.7,
            'max_tokens' => 800,
        ]);

        return $this->parseSuggestions((string) ($result['text'] ?? ''));
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You generate WhatsApp Business message template bodies. Return exactly 3 options separated by "---".
Each option must be under 400 characters, professional, and use $(variable_name) for placeholders (not {{name}}).
Do not include headers, footers, or buttons. Plain message body text only.
PROMPT;
    }

    /**
     * @return list<string>
     */
    private function parseSuggestions(string $text): array
    {
        $parts = preg_split('/\n?---\n?/', trim($text)) ?: [];

        return collect($parts)
            ->map(fn (string $part) => trim($part))
            ->filter(fn (string $part) => $part !== '')
            ->take(3)
            ->values()
            ->all();
    }
}
