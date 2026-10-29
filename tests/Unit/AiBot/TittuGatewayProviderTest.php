<?php

declare(strict_types=1);

namespace Tests\Unit\AiBot;

use App\Domains\AiBot\Services\Providers\TittuGatewayProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TittuGatewayProviderTest extends TestCase
{
    public function test_chat_requires_an_api_key(): void
    {
        $provider = new TittuGatewayProvider();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API key is required');

        $provider->chat([
            ['role' => 'user', 'content' => 'Hello'],
        ], [
            'api_key' => '   ',
        ]);
    }

    public function test_chat_sends_bearer_token_and_reads_text(): void
    {
        Http::fake([
            'https://gateway.tittu.in/v1/generate' => Http::response([
                'text' => 'PONG',
                'providerId' => 'groq',
                'modelId' => 'llama',
            ]),
        ]);

        $result = (new TittuGatewayProvider())->chat([
            ['role' => 'system', 'content' => 'Be brief'],
            ['role' => 'user', 'content' => 'Ping'],
        ], [
            'api_key' => 'tg_live_key',
            'model' => 'auto',
            'temperature' => 0,
            'max_tokens' => 16,
        ]);

        $this->assertSame('PONG', $result['text']);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://gateway.tittu.in/v1/generate'
                && $request->hasHeader('Authorization', 'Bearer tg_live_key')
                && $request['prompt'] === 'User: Ping'
                && $request['systemPrompt'] === 'Be brief'
                && ! isset($request['targetProvider']);
        });
    }

    public function test_validate_rejects_a_missing_or_unauthorized_key(): void
    {
        $provider = new TittuGatewayProvider();

        $this->assertFalse($provider->validate(''));

        Http::fake([
            'https://gateway.tittu.in/v1/generate' => Http::response([
                'error' => 'unauthorized',
            ], 401),
        ]);

        $this->assertFalse($provider->validate('bad-key'));
    }

    public function test_embed_posts_to_embeddings_endpoint(): void
    {
        Http::fake([
            'https://gateway.tittu.in/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3]],
                ],
                'usage' => ['total_tokens' => 4],
                'providerId' => 'voyage-embeddings',
            ]),
        ]);

        $result = (new TittuGatewayProvider())->embed('hello world', [
            'api_key' => 'tg_live_key',
            'model' => 'voyage-embeddings',
        ]);

        $this->assertSame([0.1, 0.2, 0.3], $result['embedding']);
        $this->assertSame(4, $result['tokens']);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://gateway.tittu.in/v1/embeddings'
                && $request->hasHeader('Authorization', 'Bearer tg_live_key')
                && $request['input'] === 'hello world'
                && $request['targetProvider'] === 'voyage-embeddings';
        });
    }

    public function test_embed_falls_back_to_v1_embed_when_embeddings_is_missing(): void
    {
        Http::fake([
            'https://gateway.tittu.in/v1/embeddings' => Http::response(['error' => 'not found'], 404),
            'https://gateway.tittu.in/v1/embed' => Http::response([
                'embedding' => [1.5, 2.5],
                'tokens' => 2,
            ]),
        ]);

        $result = (new TittuGatewayProvider())->embed('ping', [
            'api_key' => 'tg_live_key',
            'model' => 'auto',
        ]);

        $this->assertSame([1.5, 2.5], $result['embedding']);
        $this->assertSame(2, $result['tokens']);
    }

    public function test_list_models_returns_gateway_embedding_pool_fallbacks(): void
    {
        Http::fake([
            'https://gateway.tittu.in/v1/models' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $models = (new TittuGatewayProvider())->listModels('tg_live_key');

        $this->assertNotEmpty($models['chat_models']);
        $this->assertNotEmpty($models['embedding_models']);
        $this->assertSame('auto', $models['embedding_models'][0]['id']);
        $this->assertTrue(collect($models['embedding_models'])->contains(
            fn (array $row): bool => $row['id'] === 'voyage-embeddings'
        ));
    }
}
