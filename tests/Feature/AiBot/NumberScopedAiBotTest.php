<?php

declare(strict_types=1);

namespace Tests\Feature\AiBot;

use App\Domains\AiBot\Services\AiBotQueryService;
use App\Domains\AiBot\Services\AiBotRouterService;
use App\Domains\AiBot\Services\AiChatService;
use App\Domains\Integration\Services\PhoneLineService;
use App\Models\AiBot;
use App\Models\Conversation;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class NumberScopedAiBotTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_deleting_bot_on_one_line_keeps_bot_on_other_line(): void
    {
        $lineB = WhatsappLine::factory()->create(['display_name' => 'Tekpro Line']);

        $botA = AiBot::factory()->create([
            'name' => 'WapApp Bot',
            'whatsapp_line_id' => $this->testLine->id,
        ]);
        $botB = AiBot::factory()->create([
            'name' => 'Tekpro Bot',
            'whatsapp_line_id' => $lineB->id,
        ]);

        $this->actingAsTenantUser()
            ->delete(route('openai-key.bots.destroy', $botB))
            ->assertRedirect(route('openai-key.index', ['tab' => 'bot-manager']));

        $this->assertSoftDeleted('ai_bots', ['id' => $botB->id]);
        $this->assertNotSoftDeleted('ai_bots', ['id' => $botA->id]);
    }

    public function test_router_uses_only_bots_for_conversation_line(): void
    {
        $lineB = WhatsappLine::factory()->create();

        $botA = AiBot::factory()->active()->create([
            'name' => 'Line A Bot',
            'whatsapp_line_id' => $this->testLine->id,
            'is_default' => true,
        ]);
        AiBot::factory()->active()->create([
            'name' => 'Line B Bot',
            'whatsapp_line_id' => $lineB->id,
            'is_default' => true,
        ]);

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $chat = Mockery::mock(AiChatService::class);
        $chat->shouldReceive('processMessage')
            ->once()
            ->withArgs(function ($conv, $bot, $message) use ($conversation, $botA) {
                return $conv->is($conversation)
                    && $bot->is($botA)
                    && $message === 'Hello';
            })
            ->andReturn('ok');

        $router = new AiBotRouterService(app(AiBotQueryService::class), $chat);
        $this->assertSame('ok', $router->route($conversation, 'Hello'));
        $this->assertSame($botA->id, $conversation->fresh()->active_ai_bot_id);
    }

    public function test_locked_line_cannot_delete_other_line_bot(): void
    {
        $lineB = WhatsappLine::factory()->create();
        $botB = AiBot::factory()->create([
            'name' => 'Tekpro Bot',
            'whatsapp_line_id' => $lineB->id,
        ]);

        session([
            PhoneLineService::SESSION_LOCKED => true,
            PhoneLineService::SESSION_LINE_ID => $this->testLine->id,
        ]);

        $this->actingAsTenantUser()
            ->delete(route('openai-key.bots.destroy', $botB))
            ->assertForbidden();

        $this->assertNotSoftDeleted('ai_bots', ['id' => $botB->id]);
    }

    public function test_defaults_are_independent_per_line(): void
    {
        $lineB = WhatsappLine::factory()->create();

        $botA = AiBot::factory()->default()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);
        $botB = AiBot::factory()->create([
            'is_default' => false,
            'whatsapp_line_id' => $lineB->id,
        ]);

        $this->actingAsTenantUser()
            ->withHeader('Accept', 'application/json')
            ->post(route('openai-key.bots.toggle-default', $botB))
            ->assertOk()
            ->assertJson(['is_default' => true]);

        $botA->refresh();
        $botB->refresh();
        $this->assertTrue($botA->is_default);
        $this->assertTrue($botB->is_default);
    }

    public function test_router_ignores_sticky_bot_from_other_line(): void
    {
        $lineB = WhatsappLine::factory()->create();

        $botA = AiBot::factory()->active()->create([
            'name' => 'WapApp Bot',
            'whatsapp_line_id' => $this->testLine->id,
            'is_default' => true,
        ]);
        $botB = AiBot::factory()->active()->create([
            'name' => 'Tekpro Bot',
            'whatsapp_line_id' => $lineB->id,
            'is_default' => true,
        ]);

        // Conversation is on Tekpro line but sticky points at WapApp bot.
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $lineB->id,
            'active_ai_bot_id' => $botA->id,
        ]);

        $chat = Mockery::mock(AiChatService::class);
        $chat->shouldReceive('processMessage')
            ->once()
            ->withArgs(function ($conv, $bot) use ($conversation, $botB) {
                return $conv->is($conversation) && $bot->is($botB);
            })
            ->andReturn('tekpro reply');

        $router = new AiBotRouterService(app(AiBotQueryService::class), $chat);
        $this->assertSame('tekpro reply', $router->route($conversation, 'Hello'));
        $this->assertSame($botB->id, $conversation->fresh()->active_ai_bot_id);
    }

    public function test_bot_manager_filter_hides_other_line_bots_when_locked(): void
    {
        $lineB = WhatsappLine::factory()->create();
        AiBot::factory()->create([
            'name' => 'WapApp Only',
            'whatsapp_line_id' => $this->testLine->id,
        ]);
        AiBot::factory()->create([
            'name' => 'Tekpro Only',
            'whatsapp_line_id' => $lineB->id,
        ]);

        session([
            PhoneLineService::SESSION_LOCKED => true,
            PhoneLineService::SESSION_LINE_ID => $this->testLine->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('openai-key.index', ['tab' => 'bot-manager']))
            ->assertOk()
            ->assertSee('WapApp Only')
            ->assertDontSee('Tekpro Only');
    }
}
