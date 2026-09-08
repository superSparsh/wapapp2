<?php

namespace Tests\Unit\AiBot;

use App\Domains\AiBot\Services\AiBotRouterService;
use App\Domains\AiBot\Services\AiBotQueryService;
use App\Domains\AiBot\Services\AiChatService;
use App\Models\AiBot;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AiBotRouterServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private AiBotRouterService $router;

    private AiBotQueryService $queryService;

    private AiChatService&MockInterface $chatService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->queryService = new AiBotQueryService();
        $this->chatService = Mockery::mock(AiChatService::class);

        $this->router = new AiBotRouterService(
            $this->queryService,
            $this->chatService,
        );
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_returns_null_when_no_active_bots(): void
    {
        $conversation = Conversation::factory()->create(['active_ai_bot_id' => null]);

        $result = $this->router->route($conversation, 'hello');

        $this->assertNull($result);
    }

    public function test_uses_single_bot_directly_without_keyword_matching(): void
    {
        $bot = AiBot::factory()->active()->create(['name' => 'Support Bot']);
        $conversation = Conversation::factory()->create(['active_ai_bot_id' => null]);

        $this->chatService
            ->shouldReceive('processMessage')
            ->once()
            ->with(Mockery::on(fn ($c) => $c->id === $conversation->id), Mockery::on(fn ($b) => $b->id === $bot->id), 'hello')
            ->andReturn('Hi there!');

        $result = $this->router->route($conversation, 'hello');

        $this->assertSame('Hi there!', $result);
    }

    public function test_keyword_matching_selects_bot_by_name(): void
    {
        $salesBot = AiBot::factory()->active()->create(['name' => 'Revenue Helper', 'type' => 'sales', 'is_default' => false]);
        $supportBot = AiBot::factory()->active()->default()->create(['name' => 'Support Agent', 'type' => 'support']);
        $conversation = Conversation::factory()->create(['active_ai_bot_id' => null]);

        $this->chatService
            ->shouldReceive('processMessage')
            ->once()
            ->with(Mockery::any(), Mockery::on(fn ($b) => $b->id === $salesBot->id), 'tell me about revenue')
            ->andReturn('Our plans start at $10/mo.');

        $result = $this->router->route($conversation, 'tell me about revenue');

        $this->assertSame('Our plans start at $10/mo.', $result);
    }

    public function test_falls_back_to_default_bot_when_no_keyword_match(): void
    {
        AiBot::factory()->active()->create(['name' => 'Revenue Helper', 'type' => 'sales', 'is_default' => false]);
        $supportBot = AiBot::factory()->active()->default()->create(['name' => 'Support Agent', 'type' => 'support']);
        $conversation = Conversation::factory()->create(['active_ai_bot_id' => null]);

        $this->chatService
            ->shouldReceive('processMessage')
            ->once()
            ->with(Mockery::any(), Mockery::on(fn ($b) => $b->id === $supportBot->id), 'random xyz query')
            ->andReturn('I can help with that.');

        $result = $this->router->route($conversation, 'random xyz query');

        $this->assertSame('I can help with that.', $result);
    }

    public function test_uses_conversation_assigned_bot_when_active(): void
    {
        $assignedBot = AiBot::factory()->active()->create(['name' => 'Assigned Bot']);
        AiBot::factory()->active()->default()->create(['name' => 'Default Bot']);
        $conversation = Conversation::factory()->create(['active_ai_bot_id' => $assignedBot->id]);

        $this->chatService
            ->shouldReceive('processMessage')
            ->once()
            ->with(Mockery::any(), Mockery::on(fn ($b) => $b->id === $assignedBot->id), 'follow up')
            ->andReturn('Still here to help.');

        $result = $this->router->route($conversation, 'follow up');

        $this->assertSame('Still here to help.', $result);
    }

    public function test_assigns_bot_to_conversation_when_routing(): void
    {
        $bot = AiBot::factory()->active()->create(['name' => 'Support Bot']);
        $conversation = Conversation::factory()->create(['active_ai_bot_id' => null]);

        $this->chatService
            ->shouldReceive('processMessage')
            ->once()
            ->andReturn('response');

        $this->router->route($conversation, 'hello');

        $this->assertSame($bot->id, $conversation->fresh()->active_ai_bot_id);
    }
}
