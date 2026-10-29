<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Tittu AI Gateway. Requires a Bearer API key.
 * Chat: POST {base}/v1/generate
 * Embeddings: POST {base}/v1/embeddings (fallback /v1/embed)
 */
class TittuGatewayProvider implements AiProviderInterface
{
    /**
     * Default chat routing targets when /v1/models is unavailable.
     *
     * @var list<array{id: string, name: string}>
     */
    private const FALLBACK_CHAT_MODELS = [
        ['id' => 'auto', 'name' => 'Auto (gateway priority)'],
        ['id' => 'groq-free', 'name' => 'Groq Free'],
        ['id' => 'gemini-free', 'name' => 'Gemini Free'],
        ['id' => 'mistral-free', 'name' => 'Mistral Free'],
        ['id' => 'cerebras-free', 'name' => 'Cerebras Free'],
        ['id' => 'openrouter-free', 'name' => 'OpenRouter Free'],
        ['id' => 'sambanova-free', 'name' => 'SambaNova Free'],
        ['id' => 'cohere-free', 'name' => 'Cohere Free'],
        ['id' => 'dashscope-free', 'name' => 'DashScope Free'],
    ];

    /**
     * Embedding pool providers advertised by gateway.tittu.in.
     *
     * @var list<array{id: string, name: string}>
     */
    private const FALLBACK_EMBEDDING_MODELS = [
        ['id' => 'auto', 'name' => 'Auto (embeddings pool)'],
        ['id' => 'voyage-embeddings', 'name' => 'Voyage AI'],
        ['id' => 'jina-embeddings', 'name' => 'Jina AI'],
    ];

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
        $apiKey = trim((string) ($options['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException('Tittu Gateway API key is required.');
        }

        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('Embedding text is required.');
        }

        $body = [
            // OpenAI-compatible + gateway-friendly aliases.
            'input' => $text,
            'text' => $text,
        ];

        $model = trim((string) ($options['model'] ?? ''));
        if ($model !== '' && strtolower($model) !== 'auto') {
            $body['model'] = $model;
            $body['targetProvider'] = $model;
        }

        if (isset($options['dimensions'])) {
            $body['dimensions'] = (int) $options['dimensions'];
        }

        $response = $this->postEmbeddings($apiKey, $body);
        $response->throw();

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Tittu Gateway returned an invalid embedding response.');
        }

        $embedding = $this->extractEmbeddingVector($data);
        if ($embedding === []) {
            throw new RuntimeException('Tittu Gateway embedding response did not include a vector.');
        }

        return [
            'embedding' => $embedding,
            'tokens' => $this->extractEmbeddingTokens($data),
        ];
    }

    /**
     * @return array{chat_models: list<array{id: string, name: string}>, embedding_models: list<array{id: string, name: string}>}
     */
    public function listModels(string $apiKey): array
    {
        $apiKey = trim($apiKey);
        $chat = self::FALLBACK_CHAT_MODELS;
        $embed = self::FALLBACK_EMBEDDING_MODELS;

        if ($apiKey === '') {
            return [
                'chat_models' => $chat,
                'embedding_models' => $embed,
            ];
        }

        try {
            $response = $this->http($apiKey)->get('/v1/models');
            if ($response->successful()) {
                $parsed = $this->parseModelsPayload($response->json());
                if ($parsed['chat_models'] !== []) {
                    $chat = $parsed['chat_models'];
                }
                if ($parsed['embedding_models'] !== []) {
                    $embed = $parsed['embedding_models'];
                }
            }
        } catch (\Throwable) {
            // Keep fallbacks when the models endpoint is unavailable.
        }

        return [
            'chat_models' => $this->ensureAutoOption($chat),
            'embedding_models' => $this->ensureAutoOption($embed),
        ];
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

    /**
     * @param  array<string, mixed>  $body
     */
    private function postEmbeddings(string $apiKey, array $body): Response
    {
        $client = $this->http($apiKey);
        $response = $client->post('/v1/embeddings', $body);

        if ($response->status() !== 404) {
            return $response;
        }

        return $this->http($apiKey)->post('/v1/embed', $body);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<float>
     */
    private function extractEmbeddingVector(array $data): array
    {
        $candidates = [
            data_get($data, 'data.0.embedding'),
            data_get($data, 'embedding'),
            data_get($data, 'embeddings.0'),
            data_get($data, 'vector'),
            data_get($data, 'data.embedding'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || $candidate === []) {
                continue;
            }

            $vector = [];
            foreach ($candidate as $value) {
                if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
                    $vector[] = (float) $value;
                }
            }

            if ($vector !== []) {
                return $vector;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractEmbeddingTokens(array $data): int
    {
        foreach ([
            data_get($data, 'usage.total_tokens'),
            data_get($data, 'usage.prompt_tokens'),
            data_get($data, 'tokens'),
            data_get($data, 'total_tokens'),
        ] as $candidate) {
            if (is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        return 0;
    }

    /**
     * @param  mixed  $payload
     * @return array{chat_models: list<array{id: string, name: string}>, embedding_models: list<array{id: string, name: string}>}
     */
    private function parseModelsPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return ['chat_models' => [], 'embedding_models' => []];
        }

        $chat = [];
        $embed = [];

        $rows = [];
        if (array_is_list($payload)) {
            $rows = $payload;
        } elseif (is_array($payload['data'] ?? null)) {
            $rows = $payload['data'];
        } elseif (is_array($payload['models'] ?? null)) {
            $rows = $payload['models'];
        } elseif (is_array($payload['providers'] ?? null)) {
            $rows = $payload['providers'];
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = trim((string) ($row['id'] ?? $row['name'] ?? $row['providerId'] ?? ''));
            if ($id === '') {
                continue;
            }

            $name = trim((string) ($row['name'] ?? $row['label'] ?? $id));
            $entry = ['id' => $id, 'name' => $name !== '' ? $name : $id];

            $capabilities = $row['capabilities'] ?? [];
            $capabilityList = is_array($capabilities)
                ? array_map(static fn ($c): string => strtolower((string) $c), $capabilities)
                : [];

            $isEmbed = in_array('embeddings', $capabilityList, true)
                || in_array('embedding', $capabilityList, true)
                || str_contains(strtolower($id), 'embed')
                || ! empty($row['embeddingModels'])
                || (($row['type'] ?? '') === 'embedding');

            $isChat = in_array('chat', $capabilityList, true)
                || (! $isEmbed && ! empty($row['models']))
                || (($row['type'] ?? '') === 'chat');

            if ($isEmbed) {
                $embed[] = $entry;
                foreach ($row['embeddingModels'] ?? [] as $modelId) {
                    if (! is_string($modelId) && ! is_int($modelId)) {
                        continue;
                    }
                    $model = trim((string) $modelId);
                    if ($model !== '') {
                        $embed[] = ['id' => $model, 'name' => $model];
                    }
                }
            }

            if ($isChat || ! $isEmbed) {
                $chat[] = $entry;
            }
        }

        if (is_array($payload['chat_models'] ?? null)) {
            foreach ($payload['chat_models'] as $row) {
                if (is_array($row) && filled($row['id'] ?? $row['name'] ?? null)) {
                    $id = (string) ($row['id'] ?? $row['name']);
                    $chat[] = ['id' => $id, 'name' => (string) ($row['name'] ?? $id)];
                }
            }
        }

        if (is_array($payload['embedding_models'] ?? null)) {
            foreach ($payload['embedding_models'] as $row) {
                if (is_array($row) && filled($row['id'] ?? $row['name'] ?? null)) {
                    $id = (string) ($row['id'] ?? $row['name']);
                    $embed[] = ['id' => $id, 'name' => (string) ($row['name'] ?? $id)];
                }
            }
        }

        return [
            'chat_models' => $this->uniqueModels($chat),
            'embedding_models' => $this->uniqueModels($embed),
        ];
    }

    /**
     * @param  list<array{id: string, name: string}>  $models
     * @return list<array{id: string, name: string}>
     */
    private function ensureAutoOption(array $models): array
    {
        foreach ($models as $model) {
            if (strtolower($model['id']) === 'auto') {
                return $models;
            }
        }

        return array_merge([['id' => 'auto', 'name' => 'Auto (gateway priority)']], $models);
    }

    /**
     * @param  list<array{id: string, name: string}>  $models
     * @return list<array{id: string, name: string}>
     */
    private function uniqueModels(array $models): array
    {
        $seen = [];
        $out = [];

        foreach ($models as $model) {
            $id = strtolower($model['id']);
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $model;
        }

        return $out;
    }

    private function http(string $apiKey): PendingRequest
    {
        return Http::baseUrl((string) config('services.tittu_gateway.url', 'https://gateway.tittu.in'))
            ->withToken($apiKey)
            ->timeout(60)
            ->acceptJson();
    }
}
