<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\MailList;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignCrudTest extends TestCase
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
        $this->get(route('campaigns.index'))->assertRedirect();
    }

    public function test_create_step1_redirects_when_unauthenticated(): void
    {
        $this->get(route('campaigns.create.step', 1))->assertRedirect();
    }

    // ─── Index ────────────────────────────────────────────────────────────

    public function test_index_renders_with_no_campaigns(): void
    {
        $this->actingAsTenantUser()
            ->get(route('campaigns.index'))
            ->assertOk()
            ->assertViewIs('campaigns.index')
            ->assertViewHas('campaigns');
    }

    public function test_index_shows_campaigns(): void
    {
        Campaign::factory()->count(3)->create();

        $response = $this->actingAsTenantUser()
            ->get(route('campaigns.index'))
            ->assertOk();

        $response->assertViewHas('campaigns', function ($campaigns) {
            return $campaigns->count() === 3;
        });
    }

    public function test_index_search_filters_by_name(): void
    {
        Campaign::factory()->create(['name' => 'Welcome Campaign']);
        Campaign::factory()->create(['name' => 'Re-engagement']);

        $this->actingAsTenantUser()
            ->get(route('campaigns.index', ['search' => 'Welcome']))
            ->assertOk()
            ->assertViewHas('campaigns', function ($campaigns) {
                return $campaigns->count() === 1
                    && $campaigns->first()->name === 'Welcome Campaign';
            });
    }

    public function test_index_sort_by_name(): void
    {
        Campaign::factory()->create(['name' => 'Zebra Campaign']);
        Campaign::factory()->create(['name' => 'Alpha Campaign']);

        $this->actingAsTenantUser()
            ->get(route('campaigns.index', ['sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->assertViewHas('campaigns', function ($campaigns) {
                return $campaigns->first()->name === 'Alpha Campaign';
            });
    }

    public function test_index_status_filter(): void
    {
        Campaign::factory()->sending()->count(2)->create();
        Campaign::factory()->draft()->create();

        $this->actingAsTenantUser()
            ->get(route('campaigns.index', ['status' => 'sending']))
            ->assertOk()
            ->assertViewHas('campaigns', function ($campaigns) {
                return $campaigns->count() === 2;
            });
    }

    // ─── Store ────────────────────────────────────────────────────────────

    public function test_store_creates_campaign(): void
    {
        $audience = MailList::factory()->create();
        $template = Template::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('campaigns.store'), [
                'name' => 'My New Campaign',
                'whatsapp_line_id' => $this->testLine->id,
                'audience_id' => $audience->id,
                'template_id' => $template->id,
                'send_mode' => 'now',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'name' => 'My New Campaign',
            'status' => CampaignStatus::Draft->value,
        ]);
    }

    public function test_store_requires_name(): void
    {
        $this->actingAsTenantUser()
            ->post(route('campaigns.store'), [
                'send_mode' => 'now',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_store_requires_send_mode(): void
    {
        $this->actingAsTenantUser()
            ->post(route('campaigns.store'), [
                'name' => 'Test',
            ])
            ->assertSessionHasErrors('send_mode');
    }

    public function test_store_with_audience(): void
    {
        $audience = MailList::factory()->create();
        $template = Template::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('campaigns.store'), [
                'name' => 'Campaign with Audience',
                'whatsapp_line_id' => $this->testLine->id,
                'audience_id' => $audience->id,
                'template_id' => $template->id,
                'send_mode' => 'now',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Campaign with Audience',
            'audience_id' => $audience->id,
        ]);
    }

    public function test_store_with_schedule_sets_scheduled_status(): void
    {
        $audience = MailList::factory()->create();
        $template = Template::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('campaigns.store'), [
                'name' => 'Scheduled Campaign',
                'whatsapp_line_id' => $this->testLine->id,
                'audience_id' => $audience->id,
                'template_id' => $template->id,
                'send_mode' => 'schedule',
                'scheduled_at' => now()->addDays(3)->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Scheduled Campaign',
            'status' => CampaignStatus::Scheduled->value,
        ]);
    }

    // ─── Show ─────────────────────────────────────────────────────────────

    public function test_edit_draft_loads_wizard_session_and_resumes(): void
    {
        $audience = MailList::factory()->create();
        $template = Template::factory()->create();
        $campaign = Campaign::factory()->create([
            'name' => 'Editable Draft',
            'status' => CampaignStatus::Draft,
            'whatsapp_line_id' => $this->testLine->id,
            'audience_id' => $audience->id,
            'template_id' => $template->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('campaigns.edit', $campaign))
            ->assertRedirect(route('campaigns.create.step', 6));

        $wizard = session('campaign_wizard');
        $this->assertSame($campaign->id, $wizard['draft_id'] ?? null);
        $this->assertSame('Editable Draft', $wizard['name'] ?? null);
        $this->assertSame($audience->id, $wizard['audience_id'] ?? null);
        $this->assertSame($template->id, $wizard['template_id'] ?? null);
    }

    public function test_edit_rejects_non_editable_campaign(): void
    {
        $campaign = Campaign::factory()->sending()->create();

        $this->actingAsTenantUser()
            ->get(route('campaigns.edit', $campaign))
            ->assertStatus(422);
    }

    // ─── Toggle ───────────────────────────────────────────────────────────

    public function test_toggle_pauses_sending_campaign(): void
    {
        $campaign = Campaign::factory()->sending()->create();

        $this->actingAsTenantUser()
            ->patch(route('campaigns.toggle', $campaign))
            ->assertRedirect();

        $this->assertSame(CampaignStatus::Paused->value, $campaign->fresh()->status->value);
    }

    public function test_toggle_resumes_paused_campaign(): void
    {
        $campaign = Campaign::factory()->paused()->create();

        $this->actingAsTenantUser()
            ->patch(route('campaigns.toggle', $campaign))
            ->assertRedirect();

        $this->assertSame(CampaignStatus::Sending->value, $campaign->fresh()->status->value);
    }

    public function test_toggle_returns_json_for_ajax(): void
    {
        $campaign = Campaign::factory()->sending()->create();

        $response = $this->actingAsTenantUser()
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->patch(route('campaigns.toggle', $campaign));

        $response->assertJson(['success' => true]);
    }

    // ─── Duplicate ────────────────────────────────────────────────────────

    public function test_duplicate_creates_copy(): void
    {
        $campaign = Campaign::factory()->create(['name' => 'Original']);

        $this->actingAsTenantUser()
            ->post(route('campaigns.duplicate', $campaign))
            ->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Original (Copy)',
        ]);

        $this->assertDatabaseHas('campaigns', ['name' => 'Original']);
    }

    public function test_duplicate_resets_counters(): void
    {
        $campaign = Campaign::factory()->withStats(100, 80, 10, 70)->create();

        $this->actingAsTenantUser()
            ->post(route('campaigns.duplicate', $campaign))
            ->assertRedirect();

        $copy = Campaign::query()->where('name', $campaign->name . ' (Copy)')->first();
        $this->assertSame(0, $copy->total_recipients);
        $this->assertSame(0, $copy->total_delivered);
    }

    // ─── Destroy ──────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_campaign(): void
    {
        $campaign = Campaign::factory()->create();

        $this->actingAsTenantUser()
            ->delete(route('campaigns.destroy', $campaign))
            ->assertRedirect(route('campaigns.index'));

        $this->assertSoftDeleted('campaigns', ['id' => $campaign->id]);
    }

    public function test_destroy_cascades_recipients(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->count(5)->for($campaign)->create();

        $this->assertCount(5, CampaignRecipient::query()->where('campaign_id', $campaign->id)->get());

        $this->actingAsTenantUser()
            ->delete(route('campaigns.destroy', $campaign))
            ->assertRedirect();

        $this->assertSame(0, CampaignRecipient::query()->where('campaign_id', $campaign->id)->count());
    }

    public function test_destroy_returns_json_for_ajax(): void
    {
        $campaign = Campaign::factory()->create();

        $response = $this->actingAsTenantUser()
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->delete(route('campaigns.destroy', $campaign));

        $response->assertJson(['success' => true]);
    }

    // ─── Route Binding ────────────────────────────────────────────────────

    public function test_route_binding_resolves_by_uuid(): void
    {
        $campaign = Campaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertViewHas('campaign', function ($c) use ($campaign) {
                return $c->id === $campaign->id;
            });
    }
}
