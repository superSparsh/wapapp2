<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Tittu AI Gateway. Requires a Bearer API key.
 * POST {base}/v1/generate with prompt, optional systemPrompt, temperature, maxTokens.
 */
class TittuGatewayProvider implements AiProviderInterface
{
    public function chat(array $messages, array $options = []): array
    {
        $apiKey = trim((string) ($options['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('Tittu Gateway API key is required.');
        }

        $system = [];
        $turns = [];

        foreach ($messages as $message) {
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            if (($message['role'] ?? '') === 'system') {
                $system[] = $content;

                continue;
            }

            $role = ($message['role'] ?? '') === 'assistant' ? 'Assistant' : 'User';
            $turns[] = $role.': '.$content;
        }

        $body = [
            'prompt' => $turns !== [] ? implode("\n\n", $turns) : 'Hello',
            'temperature' => (float) ($options['temperature'] ?? 0.3),
            'maxTokens' => (int) ($options['max_tokens'] ?? 2048),
        ];

        if ($system !== []) {
            $body['systemPrompt'] = implode("\n\n", $system);
        }

        $model = strtolower(trim((string) ($options['model'] ?? '')));
        if ($model !== '' && $model !== 'auto') {
            $body['targetProvider'] = trim((string) $options['model']);
        }

        $response = $this->http($apiKey)->post('/v1/generate', $body);
        $response->throw();

        $data = $response->json();
        $text = is_array($data) ? (string) ($data['text'] ?? '') : '';

        return [
            'text' => $text,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
        ];
    }

    public function embed(string $text, array $options = []): array
    {
        throw new RuntimeException('Tittu Gateway does not provide embeddings. Use it for chat replies.');
    }

    public function validate(string $apiKey): bool
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return false;
        }

        try {
            $response = $this->http($apiKey)->post('/v1/generate', [
                'prompt' => 'Ping',
                'maxTokens' => 5,
                'temperature' => 0,
            ]);

            if (! $response->successful()) {
                return false;
            }

            $data = $response->json();

            return is_array($data) && (filled($data['text'] ?? null) || filled($data['providerId'] ?? null));
        } catch (\Throwable) {
            return false;
        }
    }

    private function http(string $apiKey): PendingRequest
    {
        return Http::baseUrl((string) config('services.tittu_gateway.url', 'https://gateway.tittu.in'))
            ->withToken($apiKey)
            ->timeout(60)
            ->acceptJson();
    }
}
