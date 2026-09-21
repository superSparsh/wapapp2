<?php

namespace Tests\Feature\AiBot;

use App\Enums\AiProvider;
use App\Models\AiBot;
use App\Models\AiProviderKey;
use App\Models\AiSetting;
use App\Models\AiTokenUsageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class OpenAiKeyTabTest extends TestCase
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

    // --- Tab Rendering Tests ---

    public function test_index_loads_bot_manager_tab(): void
    {
        $bot = AiBot::factory()->create(['name' => 'Sales Bot']);

        $this->actingAsTenantUser()
            ->get(route('openai-key.index'))
            ->assertOk()
            ->assertSee('AI Bot Manager')
            ->assertSee('Sales Bot');
    }

    public function test_index_loads_api_settings_tab(): void
    {
        $key = AiProviderKey::factory()->create(['chat_model' => 'gpt-4o-mini']);

        $this->actingAsTenantUser()
            ->get(route('openai-key.index', ['tab' => 'api-settings']))
            ->assertOk()
            ->assertSee('Global API Configuration')
            ->assertSee('gpt-4o-mini');
    }

    public function test_index_loads_knowledge_base_tab(): void
    {
        $bot = AiBot::factory()->active()->create();

        config([
            'ai.python_url' => 'http://ai.test.local',
            'ai.enabled' => true,
        ]);

        Http::fake([
            'http://ai.test.local/knowledge_base/*' => Http::response([
                'success' => true,
                'data' => [
                    'documents' => [[
                        'id' => '1',
                        'content' => 'Our plans start at $10/mo.',
                        'content_preview' => 'Our plans start at $10/mo.',
                        'metadata' => ['source' => 'Pricing FAQ', 'file_type' => 'text'],
                    ]],
                    'total_documents' => 1,
                ],
            ], 200),
            'http://ai.test.local/client_storage_info/*' => Http::response([
                'success' => true,
                'data' => ['document_count' => 1, 'total_size_mb' => 0.01, 'file_types' => ['text']],
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->get(route('openai-key.index', ['tab' => 'knowledge-base', 'bot' => $bot->uuid]))
            ->assertOk()
            ->assertSee('Knowledge Base Content')
            ->assertSee('Pricing FAQ');
    }

    public function test_index_loads_test_bot_tab(): void
    {
        AiBot::factory()->active()->create(['name' => 'Testable Bot']);

        $this->actingAsTenantUser()
            ->get(route('openai-key.index', ['tab' => 'test-bot']))
            ->assertOk()
            ->assertSee('Test Bot')
            ->assertSee('Testable Bot');
    }

    public function test_index_loads_usage_analytics_tab(): void
    {
        $this->actingAsTenantUser()
            ->get(route('openai-key.index', ['tab' => 'usage-analytics']))
            ->assertOk()
            ->assertSee('Usage Statistics');
    }

    public function test_index_loads_global_settings_tab(): void
    {
        AiSetting::set('ai_auto_response_enabled', '1');

        $this->actingAsTenantUser()
            ->get(route('openai-key.index', ['tab' => 'global-settings']))
            ->assertOk()
            ->assertSee('Global Settings');
    }

    public function test_index_invalid_tab_aborts(): void
    {
        $this->actingAsTenantUser()
            ->get(route('openai-key.index', ['tab' => 'non-existent']))
            ->assertNotFound();
    }

    // --- Bot Manager Actions ---

    public function test_store_bot_creates_and_redirects(): void
    {
        $this->actingAsTenantUser()
            ->post(route('openai-key.bots.store'), [
                'name' => 'New Sales Bot',
                'type' => 'sales',
                'provider' => 'openai',
                'chat_model' => 'gpt-4o-mini',
            ])
            ->assertRedirect(route('openai-key.index', ['tab' => 'bot-manager']));

        $this->assertDatabaseHas('ai_bots', ['name' => 'New Sales Bot']);
    }

    public function test_toggle_default_ajax_returns_json(): void
    {
        $bot = AiBot::factory()->create(['is_default' => false]);

        $this->actingAsTenantUser()
            ->withHeader('Accept', 'application/json')
            ->post(route('openai-key.bots.toggle-default', $bot))
            ->assertOk()
            ->assertJson(['is_default' => true]);
    }

    public function test_destroy_bot_deletes_and_redirects(): void
    {
        $bot = AiBot::factory()->create();

        $this->actingAsTenantUser()
            ->delete(route('openai-key.bots.destroy', $bot))
            ->assertRedirect(route('openai-key.index', ['tab' => 'bot-manager']));

        $this->assertSoftDeleted('ai_bots', ['id' => $bot->id]);
    }

    // --- API Settings Actions ---

    public function test_store_provider_key_creates_and_redirects(): void
    {
        $this->actingAsTenantUser()
            ->post(route('openai-key.provider-keys.store'), [
                'provider' => 'openai',
                'api_key' => 'sk-test-1234567890abcdef',
                'chat_model' => 'gpt-4o-mini',
            ])
            ->assertRedirect(route('openai-key.index', ['tab' => 'api-settings']));

        $this->assertDatabaseHas('ai_provider_keys', ['provider' => 'openai']);
    }

    public function test_validate_provider_key_ajax_returns_json(): void
    {
        Http::fake([
            'api.openai.com/v1/models' => Http::response(['data' => []], 200),
        ]);

        $key = AiProviderKey::factory()->create(['provider' => AiProvider::OpenAI]);

        $this->actingAsTenantUser()
            ->withHeader('Accept', 'application/json')
            ->post(route('openai-key.provider-keys.validate', $key))
            ->assertOk()
            ->assertJson(['is_validated' => true]);
    }

    public function test_destroy_provider_key_deletes(): void
    {
        $key = AiProviderKey::factory()->create();

        $this->actingAsTenantUser()
            ->delete(route('openai-key.provider-keys.destroy', $key))
            ->assertRedirect(route('openai-key.index', ['tab' => 'api-settings']));

        $this->assertDatabaseMissing('ai_provider_keys', ['id' => $key->id]);
    }

    public function test_list_provider_models_uses_active_key(): void
    {
        config([
            'ai.python_url' => 'http://ai.test.local',
            'ai.enabled' => true,
        ]);

        AiProviderKey::factory()->create([
            'provider' => AiProvider::OpenAI,
            'api_key' => 'sk-active',
            'is_active' => true,
        ]);

        Http::fake([
            'http://ai.test.local/list_models' => Http::response([
                'chat_models' => [['id' => 'gpt-4o-mini', 'name' => 'gpt-4o-mini']],
                'embedding_models' => [['id' => 'text-embedding-3-small', 'name' => 'text-embedding-3-small']],
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('openai-key.provider-keys.models'), ['provider' => 'openai'])
            ->assertOk()
            ->assertJsonPath('chat_models.0.id', 'gpt-4o-mini')
            ->assertJsonPath('embedding_models.0.id', 'text-embedding-3-small');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/list_models')
                && ($request->data()['api_key'] ?? null) === 'sk-active';
        });
    }

    public function test_list_provider_models_ignores_inactive_key_without_paste(): void
    {
        config([
            'ai.python_url' => 'http://ai.test.local',
            'ai.enabled' => true,
        ]);

        AiProviderKey::factory()->create([
            'provider' => AiProvider::OpenAI,
            'api_key' => 'sk-inactive',
            'is_active' => false,
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('openai-key.provider-keys.models'), ['provider' => 'openai'])
            ->assertNotFound()
            ->assertJsonPath('chat_models', []);
    }

    // --- Knowledge Base Actions (Chroma proxy — see KnowledgeBaseProxyTest) ---

    public function test_knowledge_base_mysql_store_route_removed(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('openai-key.business-info.store'),
            'KB must not be written to MySQL via openai-key.business-info.store'
        );
    }

    // --- Test Bot Action ---

    public function test_test_bot_ajax_returns_response(): void
    {
        config(['ai.enabled' => false]);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Hello! How can I help?']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 8, 'total_tokens' => 18],
            ], 200),
        ]);

        $bot = AiBot::factory()->active()->create();
        AiProviderKey::factory()->create([
            'provider' => AiProvider::OpenAI,
            'is_active' => true,
        ]);

        $this->actingAsTenantUser()
            ->withHeader('Accept', 'application/json')
            ->post(route('openai-key.test-bot'), [
                'bot_id' => $bot->uuid,
                'message' => 'Hi there!',
            ])
            ->assertOk()
            ->assertJsonPath('response', 'Hello! How can I help?')
            ->assertJsonPath('tokens', 18);
    }

    public function test_test_bot_ajax_validates_bot_id(): void
    {
        $this->actingAsTenantUser()
            ->post(route('openai-key.test-bot'), [
                'message' => 'Hello',
            ])
            ->assertSessionHasErrors('bot_id');
    }

    // --- Settings + Usage Actions ---

    public function test_save_settings_persists_setting(): void
    {
        $this->actingAsTenantUser()
            ->post(route('openai-key.settings.save'), [
                'ai_auto_response_enabled' => '1',
            ])
            ->assertRedirect(route('openai-key.index', ['tab' => 'global-settings']));

        $this->assertSame('1', AiSetting::get('ai_auto_response_enabled'));
    }

    public function test_clear_usage_deletes_all_logs(): void
    {
        $bot = AiBot::factory()->create();
        AiTokenUsageLog::query()->create([
            'ai_bot_id' => $bot->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'request_type' => 'chat',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
        ]);

        $this->actingAsTenantUser()
            ->delete(route('openai-key.usage.clear'))
            ->assertRedirect(route('openai-key.index', ['tab' => 'usage-analytics']));

        $this->assertSame(0, AiTokenUsageLog::query()->count());
    }

    // --- Auth ---

    public function test_unauthenticated_access_redirects(): void
    {
        $this->get(route('openai-key.index'))->assertRedirect(route('login'));
    }
}
