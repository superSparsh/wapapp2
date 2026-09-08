<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AzureOpenAiProvider implements AiProviderInterface
{
    public function chat(array $messages, array $options = []): array
    {
        $endpoint = $options['endpoint'] ?? '';
        $deployment = $options['model'] ?? 'gpt-4o-mini';
        $apiVersion = $options['api_version'] ?? '2024-02-01';

        $response = $this->http($options['api_key'] ?? '', $endpoint)
            ->post("/openai/deployments/{$deployment}/chat/completions?api-version={$apiVersion}", [
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
        $endpoint = $options['endpoint'] ?? '';
        $deployment = $options['model'] ?? 'text-embedding-3-small';
        $apiVersion = $options['api_version'] ?? '2024-02-01';

        $response = $this->http($options['api_key'] ?? '', $endpoint)
            ->post("/openai/deployments/{$deployment}/embeddings?api-version={$apiVersion}", [
                'input' => $text,
            ]);

        $response->throw();

        $data = $response->json();

        return [
            'embedding' => $data['data'][0]['embedding'] ?? [],
            'tokens' => (int) ($data['usage']['total_tokens'] ?? 0),
        ];
    }

    public function validate(string $apiKey): bool
    {
        // Azure requires an endpoint, so basic validation is limited
        return ! empty($apiKey);
    }

    private function http(string $apiKey, string $endpoint): PendingRequest
    {
        return Http::baseUrl($endpoint)
            ->withHeaders(['api-key' => $apiKey])
            ->timeout(60)
            ->acceptJson();
    }
}
