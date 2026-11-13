<?php

declare(strict_types=1);

namespace Tests\Feature\AiBot;

use App\Domains\AiBot\Services\AiChatService;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Enums\AiProvider;
use App\Models\AiBot;
use App\Models\AiProviderKey;
use App\Models\AiSetting;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AiChatProviderExhaustedTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        config([
            'ai.enabled' => false,
            'ai.python_url' => null,
        ]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_whatsapp_receives_configured_message_when_providers_exhausted(): void
    {
        AiSetting::set(
            AiChatService::PROVIDER_EXHAUSTED_SETTING_KEY,
            'Hang tight — we will reply soon.',
        );

        $bot = AiBot::factory()->active()->create([
            'provider' => AiProvider::Tittu,
            'chat_model' => 'auto',
        ]);
        AiProviderKey::factory()->create([
            'provider' => AiProvider::Tittu,
            'is_active' => true,
        ]);

        Http::fake([
            'gateway.tittu.in/*' => Http::response([
                'error' => 'all_providers_exhausted',
                'message' => 'All eligible AI providers are exhausted or cooling down.',
                'cooldownRemainingSeconds' => 90,
            ], 503),
        ]);

        $sent = [];
        $this->mock(InboxOutboundService::class, function ($mock) use (&$sent): void {
            $mock->shouldReceive('sendText')
                ->once()
                ->andReturnUsing(function ($conversation, string $body) use (&$sent) {
                    $sent[] = $body;

                    return new Message([
                        'id' => 1,
                        'body' => $body,
                    ]);
                });
        });

        $conversation = Conversation::factory()->create();
        $result = app(AiChatService::class)->processMessage($conversation, $bot, 'Hello');

        $this->assertSame('Hang tight — we will reply soon.', $result);
        $this->assertSame(['Hang tight — we will reply soon.'], $sent);
    }

    public function test_empty_exhausted_setting_skips_whatsapp_courtesy(): void
    {
        AiSetting::set(AiChatService::PROVIDER_EXHAUSTED_SETTING_KEY, '');

        $bot = AiBot::factory()->active()->create([
            'provider' => AiProvider::Tittu,
            'chat_model' => 'auto',
        ]);
        AiProviderKey::factory()->create([
            'provider' => AiProvider::Tittu,
            'is_active' => true,
        ]);

        Http::fake([
            'gateway.tittu.in/*' => Http::response([
                'error' => 'all_providers_exhausted',
                'message' => 'cooling down',
            ], 503),
        ]);

        $this->mock(InboxOutboundService::class, function ($mock): void {
            $mock->shouldReceive('sendText')->never();
        });

        $conversation = Conversation::factory()->create();
        $result = app(AiChatService::class)->processMessage($conversation, $bot, 'Hello');

        $this->assertNull($result);
    }
}
