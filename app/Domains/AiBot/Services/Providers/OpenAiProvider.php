<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderInterface
{
    private const BASE_URL = 'https://api.openai.com/v1';

    public function chat(array $messages, array $options = []): array
    {
        $response = $this->http($options['api_key'] ?? '')->post('/chat/completions', [
            'model' => $options['model'] ?? 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens' => $options['max_tokens'] ?? 2048,
        ]);

        $response->throw();

        $data = $response->json();

        return [
            'text' => $data['choices'][0]['message']['content'] ?? '',
            'prompt_tokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($data['usage']['total_tokens'] ?? 0),
        ];
    }

    public function embed(string $text, array $options = []): array
    {
        $body = [
            'model' => $options['model'] ?? 'text-embedding-3-small',
            'input' => $text,
        ];

        if (isset($options['dimensions'])) {
            $body['dimensions'] = (int) $options['dimensions'];
        }

        $response = $this->http($options['api_key'] ?? '')->post('/embeddings', $body);

        $response->throw();

        $data = $response->json();

        return [
            'embedding' => $data['data'][0]['embedding'] ?? [],
            'tokens' => (int) ($data['usage']['total_tokens'] ?? 0),
        ];
    }

    public function validate(string $apiKey): bool
    {
        try {
            $response = $this->http($apiKey)->get('/models');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function http(string $apiKey): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken($apiKey)
            ->timeout(60)
            ->acceptJson();
    }
}
