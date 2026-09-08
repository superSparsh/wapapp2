<?php

namespace Tests\Feature\AiBot;

use App\Enums\AiProvider;
use App\Models\AiBot;
use App\Models\AiProviderKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AiBotCrudTest extends TestCase
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

    public function test_index_page_loads(): void
    {
        $this->actingAsTenantUser()
            ->get(route('ai-bots.index'))
            ->assertOk();
    }

    public function test_create_page_loads(): void
    {
        $this->actingAsTenantUser()
            ->get(route('ai-bots.create'))
            ->assertOk();
    }

    public function test_store_creates_bot(): void
    {
        $this->actingAsTenantUser()
            ->post(route('ai-bots.store'), [
                'name' => 'Support Bot',
                'provider' => 'openai',
                'chat_model' => 'gpt-4o-mini',
                'temperature' => 0.3,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_bots', ['name' => 'Support Bot']);
    }

    public function test_store_validates_name(): void
    {
        $this->actingAsTenantUser()
            ->post(route('ai-bots.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_show_page_loads(): void
    {
        $bot = AiBot::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('ai-bots.show', $bot))
            ->assertOk()
            ->assertSee($bot->name);
    }

    public function test_update_modifies_bot(): void
    {
        $bot = AiBot::factory()->create();

        $this->actingAsTenantUser()
            ->put(route('ai-bots.update', $bot), [
                'name' => 'Updated Bot',
                'temperature' => 0.5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_bots', ['id' => $bot->id, 'name' => 'Updated Bot']);
    }

    public function test_toggle_default(): void
    {
        $bot = AiBot::factory()->create(['is_default' => false]);

        $this->actingAsTenantUser()
            ->patch(route('ai-bots.toggle-default', $bot))
            ->assertRedirect();

        $bot->refresh();
        $this->assertTrue($bot->is_default);
    }

    public function test_toggle_default_unsets_others(): void
    {
        $bot1 = AiBot::factory()->default()->create();
        $bot2 = AiBot::factory()->create(['is_default' => false]);

        $this->actingAsTenantUser()
            ->patch(route('ai-bots.toggle-default', $bot2))
            ->assertRedirect();

        $bot1->refresh();
        $bot2->refresh();
        $this->assertFalse($bot1->is_default);
        $this->assertTrue($bot2->is_default);
    }

    public function test_destroy_deletes_bot(): void
    {
        $bot = AiBot::factory()->create();

        $this->actingAsTenantUser()
            ->delete(route('ai-bots.destroy', $bot))
            ->assertRedirect();

        $this->assertSoftDeleted('ai_bots', ['id' => $bot->id]);
    }

    public function test_provider_keys_index_loads(): void
    {
        $this->actingAsTenantUser()
            ->get(route('ai-bots.provider-keys.index'))
            ->assertOk();
    }

    public function test_store_provider_key(): void
    {
        $this->actingAsTenantUser()
            ->post(route('ai-bots.provider-keys.store'), [
                'provider' => 'openai',
                'api_key' => 'sk-test-1234567890abcdef',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_provider_keys', ['provider' => 'openai']);
    }

    public function test_usage_page_loads(): void
    {
        $bot = AiBot::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('ai-bots.usage', $bot))
            ->assertOk();
    }

    public function test_unauthenticated_access_redirects(): void
    {
        $this->get(route('ai-bots.index'))->assertRedirect(route('login'));
    }
}
