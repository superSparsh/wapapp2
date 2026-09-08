<?php

namespace Tests\Feature\Templates;

use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\Templates\Enums\TemplateSource;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Support\TemplateCatalogCache;
use App\Models\TeamMember;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TemplateCatalogTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        TemplateCatalogCache::flush();

        $this->mock(InboxOutboundService::class, function ($mock): void {
            $mock->shouldReceive('listTemplates')->andReturn([
                [
                    'code' => 'welcome_offer',
                    'name' => 'Welcome Offer',
                    'language' => 'en_GB',
                    'category' => 'MARKETING',
                ],
                [
                    'code' => 'order_update',
                    'name' => 'Order Update',
                    'language' => 'en_GB',
                    'category' => 'UTILITY',
                ],
            ]);
        });

        // CAMS catalog sync is explicit only (no auto-sync on page load).
        app(\App\Domains\Templates\Services\TemplateRegistryService::class)->refresh();
    }

    protected function tearDown(): void
    {
        TemplateCatalogCache::flush();
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_owner_can_view_templates_index(): void
    {
        $this->actingAsTenantUser()
            ->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Welcome Offer')
            ->assertSee('Order Update');
    }

    public function test_templates_can_be_filtered_by_search_and_category(): void
    {
        $this->actingAsTenantUser()
            ->get(route('templates.index', ['q' => 'order']))
            ->assertOk()
            ->assertSee('Order Update')
            ->assertDontSee('Welcome Offer');

        $this->actingAsTenantUser()
            ->get(route('templates.index', ['category' => 'MARKETING']))
            ->assertOk()
            ->assertSee('Welcome Offer')
            ->assertDontSee('Order Update');
    }

    public function test_templates_can_be_filtered_by_type(): void
    {
        Template::factory()->create([
            'code' => null,
            'name' => 'Local Draft',
            'whatsapp_line_id' => $this->testLine->id,
            'status' => TemplateStatus::Draft,
            'source' => TemplateSource::Local,
        ]);

        $this->actingAsTenantUser()
            ->get(route('templates.index', ['type' => 'Regular']))
            ->assertOk()
            ->assertSee('Welcome Offer')
            ->assertDontSee('Local Draft');

        $this->actingAsTenantUser()
            ->get(route('templates.index', ['type' => 'Draft']))
            ->assertOk()
            ->assertSee('Local Draft')
            ->assertDontSee('Welcome Offer');
    }

    public function test_refresh_resyncs_templates(): void
    {
        Template::factory()->create([
            'code' => 'stale_template',
            'name' => 'Stale Template',
            'whatsapp_line_id' => $this->testLine->id,
            'status' => TemplateStatus::Approved,
            'source' => TemplateSource::Cams,
            'synced_at' => now()->subHour(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('templates.index', ['refresh' => 1]))
            ->assertRedirect(route('templates.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('templates', ['code' => 'welcome_offer']);
    }

    public function test_team_member_without_permission_cannot_view_templates(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('templates.index'))
            ->assertForbidden();
    }
}
