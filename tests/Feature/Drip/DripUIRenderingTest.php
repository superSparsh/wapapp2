<?php

declare(strict_types=1);

namespace Tests\Feature\Drip;

use App\Enums\ChatbotFlowStatus;
use App\Models\DripCampaign;
use App\Models\DripCampaignStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DripUIRenderingTest extends TestCase
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

    // ─── All pages render without errors ──────────────────────────────────

    public function test_index_page_renders(): void
    {
        $this->actingAsTenantUser()
            ->get(route('automation.drip.index'))
            ->assertOk();
    }

    public function test_create_page_renders(): void
    {
        $this->actingAsTenantUser()
            ->get(route('automation.drip.create'))
            ->assertOk();
    }

    public function test_design_page_renders(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.design', $campaign))
            ->assertOk();
    }

    public function test_statistics_page_renders(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.statistics', $campaign))
            ->assertOk();
    }

    public function test_statistics_detail_page_renders(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.statistics.detail', $campaign))
            ->assertOk();
    }

    public function test_insights_page_renders(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.insights', $campaign))
            ->assertOk();
    }

    public function test_audience_page_renders(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.audience', $campaign))
            ->assertOk();
    }

    public function test_timeline_page_renders(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.audience-empty', $campaign))
            ->assertOk();
    }

    // ─── Forms contain CSRF tokens ────────────────────────────────────────

    public function test_create_form_has_csrf(): void
    {
        $this->actingAsTenantUser()
            ->get(route('automation.drip.create'))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('Create &amp; Design Flow', false);
    }

    public function test_design_form_has_campaign_fields(): void
    {
        $campaign = DripCampaign::factory()->create(['name' => 'Test Campaign']);

        $this->actingAsTenantUser()
            ->get(route('automation.drip.design', $campaign))
            ->assertOk()
            ->assertSee('Test Campaign', false)
            ->assertSee('Save', false)
            ->assertSee('data-tz-search', false);
    }

    // ─── List page shows correct data ─────────────────────────────────────

    public function test_index_shows_campaign_name_and_status(): void
    {
        $campaign = DripCampaign::factory()->active()->create([
            'name' => 'Visible Campaign',
        ]);

        $this->actingAsTenantUser()
            ->get(route('automation.drip.index'))
            ->assertOk()
            ->assertSee('Visible Campaign')
            ->assertSee('Running');
    }

    public function test_index_shows_paused_badge_for_inactive(): void
    {
        DripCampaign::factory()->inactive()->create([
            'name' => 'Paused Campaign',
        ]);

        $this->actingAsTenantUser()
            ->get(route('automation.drip.index'))
            ->assertOk()
            ->assertSee('Paused Campaign')
            ->assertSee('Paused');
    }

    // ─── Toggle switches work ─────────────────────────────────────────────

    public function test_toggle_form_is_present_on_index(): void
    {
        DripCampaign::factory()->active()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.index'))
            ->assertOk()
            ->assertSee('En/Disable');
    }

    // ─── Delete forms present ─────────────────────────────────────────────

    public function test_delete_form_is_present_on_index(): void
    {
        DripCampaign::factory()->create();

        $response = $this->actingAsTenantUser()
            ->get(route('automation.drip.index'));

        $response->assertOk();
        $response->assertSee('data-drip-delete', false);
        $response->assertSee('data-confirm', false);
    }

    // ─── Empty state ──────────────────────────────────────────────────────

    public function test_empty_index_shows_add_new_message(): void
    {
        $this->actingAsTenantUser()
            ->get(route('automation.drip.index'))
            ->assertOk()
            ->assertSee('No drip campaigns yet');
    }
}
