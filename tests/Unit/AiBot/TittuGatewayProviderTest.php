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
}
