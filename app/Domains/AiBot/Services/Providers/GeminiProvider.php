<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProviderInterface
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? 'gemini-1.5-flash';
        $apiKey = $options['api_key'] ?? '';

        // Convert OpenAI-style messages to Gemini format
        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemInstruction = $msg['content'];
                continue;
            }

            $contents[] = [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.3,
                'maxOutputTokens' => $options['max_tokens'] ?? 2048,
            ],
        ];

        if ($systemInstruction !== null) {
            $body['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        $response = $this->http($apiKey)->post("/models/{$model}:generateContent", $body);

        $response->throw();

        $data = $response->json();

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $promptTokens = (int) ($data['usageMetadata']['promptTokenCount'] ?? 0);
        $completionTokens = (int) ($data['usageMetadata']['candidatesTokenCount'] ?? 0);

        return [
            'text' => $text,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
        ];
    }

    public function embed(string $text, array $options = []): array
    {
        $model = $options['model'] ?? 'text-embedding-004';
        $apiKey = $options['api_key'] ?? '';

        $response = $this->http($apiKey)->post("/models/{$model}:embedContent", [
            'model' => "models/{$model}",
            'content' => ['parts' => [['text' => $text]]],
        ]);

        $response->throw();

        $data = $response->json();

        return [
            'embedding' => $data['embedding']['values'] ?? [],
            'tokens' => 0, // Gemini doesn't return token count for embeddings
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
            ->withQueryParameters(['key' => $apiKey])
            ->timeout(60)
            ->acceptJson();
    }
}
