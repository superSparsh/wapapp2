<?php

declare(strict_types=1);

namespace Tests\Feature\AiBot;

use App\Models\AiBot;
use App\Models\AiProviderKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class KnowledgeBaseProxyTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        config([
            'ai.python_url' => 'http://ai.test.local',
            'ai.enabled' => true,
            'ai.timeout' => 5,
            'ai.connect_timeout' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_knowledge_base_list_proxies_to_ai_service(): void
    {
        $bot = AiBot::factory()->create(['is_default' => true, 'name' => 'KB Bot']);

        Http::fake([
            'http://ai.test.local/*' => Http::response([
                'success' => true,
                'data' => [
                    'documents' => [[
                        'id' => 'c1',
                        'content' => 'Full chunk text about shipping.',
                        'content_preview' => 'Full chunk text…',
                        'metadata' => ['source' => 'manual', 'file_type' => 'text'],
                    ]],
                    'total_documents' => 1,
                ],
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->getJson(route('openai-key.knowledge-base', ['bot_id' => $bot->uuid]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_documents', 1)
            ->assertJsonPath('data.documents.0.metadata.source', 'manual');
    }

    public function test_add_manual_content_proxies_with_provider_key(): void
    {
        $bot = AiBot::factory()->create(['is_default' => true]);
        AiProviderKey::factory()->create([
            'provider' => 'openai',
            'api_key' => 'sk-test',
            'is_active' => true,
            'is_validated' => true,
            'chat_model' => 'gpt-4o-mini',
            'embedding_model' => 'text-embedding-3-small',
        ]);

        Http::fake([
            'http://ai.test.local/add_manual_content' => Http::response([
                'success' => true,
                'message' => 'Manual content added and indexed successfully',
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('openai-key.knowledge-base.add-manual'), [
                'bot_id' => $bot->uuid,
                'documents' => ['We ship worldwide in 3 days.'],
                'metadata' => [['source' => 'manual', 'file_type' => 'text']],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), '/add_manual_content')
                && ($data['documents'][0] ?? null) === 'We ship worldwide in 3 days.'
                && filled($data['api_key'] ?? null)
                && filled($data['client_id'] ?? null);
        });
    }

    public function test_chroma_keys_use_legacy_ids_when_present(): void
    {
        $tenant = tenant();
        $settings = $tenant->settings ?? [];
        $settings['legacy_customer_id'] = 42;
        $tenant->forceFill(['settings' => $settings])->save();

        $bot = AiBot::factory()->create([
            'is_default' => true,
            'legacy_bot_id' => 99,
        ]);

        Http::fake([
            'http://ai.test.local/knowledge_base/*' => Http::response([
                'success' => true,
                'data' => ['documents' => [], 'total_documents' => 0],
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->getJson(route('openai-key.knowledge-base', ['bot_id' => $bot->uuid]))
            ->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/knowledge_base/42')
                && str_contains($request->url(), 'bot_id=99');
        });
    }

    public function test_knowledge_base_tab_shows_chroma_documents_not_mysql(): void
    {
        $bot = AiBot::factory()->create(['is_default' => true, 'name' => 'Live Bot']);

        Http::fake([
            'http://ai.test.local/knowledge_base/*' => Http::response([
                'success' => true,
                'data' => [
                    'documents' => [[
                        'id' => 'x',
                        'content' => 'Chroma chunk content here',
                        'content_preview' => 'Chroma chunk content here',
                        'metadata' => ['source' => 'https://example.com', 'file_type' => 'url'],
                    ]],
                    'total_documents' => 1,
                ],
            ], 200),
            'http://ai.test.local/client_storage_info/*' => Http::response([
                'success' => true,
                'data' => [
                    'document_count' => 1,
                    'total_size_mb' => 0.12,
                    'file_types' => ['url'],
                ],
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->get(route('openai-key.index', ['tab' => 'knowledge-base', 'bot' => $bot->uuid]))
            ->assertOk()
            ->assertSee('Knowledge Base Content')
            ->assertSee('Chroma chunk content here')
            ->assertSee('https://example.com')
            ->assertSee('Total Chunks:');
    }

    public function test_ai_service_error_surfaces_on_list(): void
    {
        AiBot::factory()->create(['is_default' => true]);

        Http::fake([
            'http://ai.test.local/*' => Http::response(['error' => 'Chroma down'], 503),
        ]);

        $this->actingAsTenantUser()
            ->getJson(route('openai-key.knowledge-base'))
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }
}
