<?php

namespace Tests\Unit\Templates;

use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\Templates\Services\BuiltinVariableCatalog;
use App\Domains\Templates\Services\TemplatePreviewService;
use App\Domains\Templates\Services\TemplateRegistryService;
use App\Domains\Templates\Support\TemplateCatalogCache;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TemplateDomainTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        TemplateCatalogCache::flush();
    }

    protected function tearDown(): void
    {
        TemplateCatalogCache::flush();
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_builtin_variable_catalog_returns_categorized_items(): void
    {
        $catalog = app(BuiltinVariableCatalog::class)->all();

        $this->assertNotEmpty($catalog);
        $this->assertSame('$(first_name)', collect($catalog)->firstWhere('name', 'first_name')['syntax']);
    }

    public function test_template_registry_syncs_and_lists_templates(): void
    {
        $line = WhatsappLine::query()->firstOrFail();

        $this->mock(InboxOutboundService::class, function ($mock) use ($line): void {
            $mock->shouldReceive('listTemplates')
                ->once()
                ->with(\Mockery::on(fn (WhatsappLine $passedLine): bool => $passedLine->is($line)))
                ->andReturn([
                    ['code' => 'a', 'name' => 'Alpha Promo', 'language' => 'en_GB', 'category' => 'MARKETING'],
                    ['code' => 'b', 'name' => 'Beta Utility', 'language' => 'en_GB', 'category' => 'UTILITY'],
                ]);
        });

        $registry = app(TemplateRegistryService::class);
        $registry->refresh();

        $this->assertCount(2, $registry->options());
        $this->assertSame(['MARKETING', 'UTILITY'], $registry->categories());
    }

    public function test_template_registry_does_not_auto_sync_on_list_or_options(): void
    {
        $this->mock(InboxOutboundService::class, function ($mock): void {
            $mock->shouldReceive('listTemplates')->never();
        });

        $registry = app(TemplateRegistryService::class);

        $this->assertSame([], $registry->options());
        $this->assertCount(0, $registry->listForTable());
    }

    public function test_prune_cams_duplicates_keeps_local_legacy_rows(): void
    {
        Template::factory()->create([
            'name' => 'Welcome',
            'code' => 'welcome_legacy',
            'language' => 'en',
            'source' => \App\Domains\Templates\Enums\TemplateSource::Local,
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        Template::factory()->create([
            'name' => 'Welcome',
            'code' => 'CAMS_CODE_XYZ',
            'language' => 'en_GB',
            'source' => \App\Domains\Templates\Enums\TemplateSource::Cams,
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $deleted = app(TemplateRegistryService::class)->pruneCamsDuplicatesOfLocal();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseHas('templates', ['code' => 'welcome_legacy', 'source' => 'local']);
        $this->assertDatabaseMissing('templates', ['code' => 'CAMS_CODE_XYZ']);
    }

    public function test_template_preview_service_builds_preview_from_template(): void
    {
        $template = Template::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'body_preview' => 'Hello customer',
            'payload' => array_merge(Template::defaultPayload(), [
                'buttons' => [['text' => 'Shop Now', 'type' => 'url']],
            ]),
        ]);

        $preview = app(TemplatePreviewService::class)->forTemplate($template);

        $this->assertSame('Hello customer', $preview['body']);
        $this->assertSame('Shop Now', $preview['buttons'][0]['text']);
    }
}
