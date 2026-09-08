<?php

declare(strict_types=1);

namespace Tests\Feature\Drip;

use App\Enums\ChatbotFlowStatus;
use App\Models\DripCampaign;
use App\Models\ChatbotFlow;
use App\Models\DripCampaignStat;
use App\Models\MailList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DripCampaignCrudTest extends TestCase
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

    // ─── Auth ─────────────────────────────────────────────────────────────

    public function test_index_redirects_when_unauthenticated(): void
    {
        $this->get(route('automation.drip.index'))->assertRedirect();
    }

    public function test_create_redirects_when_unauthenticated(): void
    {
        $this->get(route('automation.drip.create'))->assertRedirect();
    }

    // ─── Index ────────────────────────────────────────────────────────────

    public function test_index_renders_with_no_campaigns(): void
    {
        $this->actingAsTenantUser()
            ->get(route('automation.drip.index'))
            ->assertOk()
            ->assertViewIs('automation.drip')
            ->assertViewHas('campaigns');
    }

    public function test_index_shows_drip_campaigns_only(): void
    {
        // Create drip campaigns
        DripCampaign::factory()->count(3)->create();

        // Chatbot flows live in a separate table and must not affect drip listing.
        ChatbotFlow::factory()->create();

        $response = $this->actingAsTenantUser()
            ->get(route('automation.drip.index'))
            ->assertOk();

        $response->assertViewHas('campaigns', function ($campaigns) {
            return $campaigns->count() === 3;
        });
    }

    public function test_index_search_filters_by_name(): void
    {
        DripCampaign::factory()->create(['name' => 'Welcome Series']);
        DripCampaign::factory()->create(['name' => 'Re-engagement']);

        $this->actingAsTenantUser()
            ->get(route('automation.drip.index', ['search' => 'Welcome']))
            ->assertOk()
            ->assertViewHas('campaigns', function ($campaigns) {
                return $campaigns->count() === 1
                    && $campaigns->first()->name === 'Welcome Series';
            });
    }

    public function test_index_sort_by_name(): void
    {
        DripCampaign::factory()->create(['name' => 'Zebra Campaign']);
        DripCampaign::factory()->create(['name' => 'Alpha Campaign']);

        $this->actingAsTenantUser()
            ->get(route('automation.drip.index', ['sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->assertViewHas('campaigns', function ($campaigns) {
                return $campaigns->first()->name === 'Alpha Campaign';
            });
    }

    // ─── Create / Store ───────────────────────────────────────────────────

    public function test_create_form_renders(): void
    {
        $this->actingAsTenantUser()
            ->get(route('automation.drip.create'))
            ->assertOk()
            ->assertViewIs('automation.drip-create')
            ->assertSee('Automation Trigger')
            ->assertSee('Welcome new subscribers');
    }

    public function test_store_creates_drip_campaign(): void
    {
        $this->actingAsTenantUser()
            ->post(route('automation.drip.store'), [
                'name' => 'My New Drip',
                'trigger_type' => 'subscriber_optin',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('drip_campaigns', [
            'name' => 'My New Drip',
            'trigger_type' => 'welcome-new-subscriber',
        ]);
    }

    public function test_store_requires_name(): void
    {
        $this->actingAsTenantUser()
            ->post(route('automation.drip.store'), [
                'trigger_type' => 'api',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_store_with_audience(): void
    {
        $audience = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('automation.drip.store'), [
                'name' => 'Drip with Audience',
                'audience_id' => $audience->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('drip_campaigns', [
            'name' => 'Drip with Audience',
            'audience_id' => $audience->id,
        ]);
    }

    // ─── Show (redirect) ─────────────────────────────────────────────────

    public function test_show_redirects_to_design(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.show', $campaign))
            ->assertRedirect(route('automation.drip.design', $campaign));
    }

    // ─── Design / Edit ───────────────────────────────────────────────────

    public function test_design_page_renders(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.design', $campaign))
            ->assertOk()
            ->assertViewIs('automation.drip-design')
            ->assertViewHas('campaign')
            ->assertViewHas('audiences')
            ->assertViewHas('timezones')
            ->assertSee('Automation Trigger')
            ->assertSee('Abandoned cart reminder');
    }

    // ─── Update Settings ─────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function settingsPayload(DripCampaign $campaign, array $overrides = []): array
    {
        $audienceId = $campaign->audience_id ?? MailList::factory()->create()->id;

        return array_merge([
            'name' => $campaign->name,
            'audience_id' => $audienceId,
            'timezone' => $campaign->timezone ?? 'Asia/Kolkata',
            'start_date' => $campaign->start_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'end_date' => $campaign->end_date?->format('Y-m-d') ?? now()->addMonth()->format('Y-m-d'),
            'trigger_type' => $campaign->trigger_type,
            'trigger_options' => $campaign->trigger_options ?? [],
        ], $overrides);
    }

    public function test_update_settings_requires_audience_when_full_form_submitted(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->put(route('automation.drip.design.update', $campaign), $this->settingsPayload($campaign, [
                'audience_id' => '',
            ]))
            ->assertSessionHasErrors('audience_id');
    }

    public function test_update_settings_requires_valid_date_range(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->put(route('automation.drip.design.update', $campaign), $this->settingsPayload($campaign, [
                'start_date' => '2026-12-31',
                'end_date' => '2026-01-01',
            ]))
            ->assertSessionHasErrors('end_date');
    }

    public function test_store_validates_specific_date_trigger_options(): void
    {
        $this->actingAsTenantUser()
            ->post(route('automation.drip.store'), [
                'name' => 'Date Trigger Drip',
                'trigger_type' => 'specific-date',
                'trigger_options' => [],
            ])
            ->assertSessionHasErrors(['trigger_options.date', 'trigger_options.at']);
    }

    public function test_update_settings_changes_name(): void
    {
        $campaign = DripCampaign::factory()->create(['name' => 'Old Name']);

        $this->actingAsTenantUser()
            ->put(route('automation.drip.design.update', $campaign), [
                'name' => 'Updated Name',
            ])
            ->assertRedirect(route('automation.drip.design', $campaign));

        $this->assertDatabaseHas('drip_campaigns', [
            'id' => $campaign->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_settings_changes_timezone(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->put(route('automation.drip.design.update', $campaign), [
                'name' => $campaign->name,
                'timezone' => 'America/New_York',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('drip_campaigns', [
            'id' => $campaign->id,
            'timezone' => 'America/New_York',
        ]);
    }

    public function test_update_settings_changes_trigger_type(): void
    {
        $campaign = DripCampaign::factory()->create(['trigger_type' => 'welcome-new-subscriber']);

        $this->actingAsTenantUser()
            ->put(route('automation.drip.design.update', $campaign), $this->settingsPayload($campaign, [
                'trigger_type' => 'specific-date',
                'trigger_options' => [
                    'date' => '2026-12-25',
                    'at' => '10:00',
                ],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('drip_campaigns', [
            'id' => $campaign->id,
            'trigger_type' => 'specific-date',
        ]);

        $campaign->refresh();
        $this->assertSame('2026-12-25', $campaign->trigger_options['date'] ?? null);
    }

    // ─── Destroy ──────────────────────────────────────────────────────────

    public function test_destroy_deletes_campaign(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->delete(route('automation.drip.destroy', $campaign))
            ->assertRedirect(route('automation.drip.index'));

        $this->assertSoftDeleted('drip_campaigns', ['id' => $campaign->id]);
    }

    public function test_destroy_cascades_stats_and_states(): void
    {
        $campaign = DripCampaign::factory()->withStats(3)->create();

        $this->assertCount(3, DripCampaignStat::query()->where('drip_campaign_id', $campaign->id)->get());

        $this->actingAsTenantUser()
            ->delete(route('automation.drip.destroy', $campaign))
            ->assertRedirect();

        $this->assertSame(0, DripCampaignStat::query()->where('drip_campaign_id', $campaign->id)->count());
    }

    // ─── Toggle ───────────────────────────────────────────────────────────

    public function test_toggle_activates_draft_campaign(): void
    {
        $campaign = DripCampaign::factory()->create([
            'status' => ChatbotFlowStatus::Draft,
        ]);

        $this->actingAsTenantUser()
            ->patch(route('automation.drip.toggle', $campaign))
            ->assertRedirect(route('automation.drip.index'));

        $this->assertSame(ChatbotFlowStatus::Active->value, $campaign->fresh()->status->value);
    }

    public function test_toggle_deactivates_active_campaign(): void
    {
        $campaign = DripCampaign::factory()->active()->create();

        $this->actingAsTenantUser()
            ->patch(route('automation.drip.toggle', $campaign))
            ->assertRedirect();

        $this->assertSame(ChatbotFlowStatus::Inactive->value, $campaign->fresh()->status->value);
    }

    // ─── Duplicate ────────────────────────────────────────────────────────

    public function test_duplicate_creates_copy(): void
    {
        $campaign = DripCampaign::factory()->create(['name' => 'Original']);

        $this->actingAsTenantUser()
            ->post(route('automation.drip.duplicate', $campaign))
            ->assertRedirect();

        $this->assertDatabaseHas('drip_campaigns', [
            'name' => 'Original (Copy)',
        ]);

        // Original should still exist
        $this->assertDatabaseHas('drip_campaigns', ['name' => 'Original']);
    }

    // ─── Route Binding ────────────────────────────────────────────────────

    public function test_route_binding_rejects_chatbot_flow_uuid(): void
    {
        $chatbot = ChatbotFlow::factory()->create();

        $this->actingAsTenantUser()
            ->get('/automation/drip/' . $chatbot->uuid . '/design')
            ->assertNotFound();
    }
}
