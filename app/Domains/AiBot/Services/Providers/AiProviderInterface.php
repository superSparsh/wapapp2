<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services\Providers;

interface AiProviderInterface
{
    /**
     * Send a chat completion request.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options  model, temperature, max_tokens, etc.
     * @return array{text: string, prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Generate an embedding vector for the given text.
     *
     * @param  array<string, mixed>  $options  model, dimensions, etc.
     * @return array{embedding: array<float>, tokens: int}
     */
    public function embed(string $text, array $options = []): array;

    /**
     * Validate that the API key is working.
     */
    public function validate(string $apiKey): bool;
}
