<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsappFlow;

use App\Enums\WhatsappFlowStatus;
use App\Enums\WhatsappFlowSubmitAction;
use App\Models\WhatsappFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WhatsappFlowCrudTest extends TestCase
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

    public function test_store_creates_flow(): void
    {
        $this->actingAsTenantUser()
            ->post(route('whatsapp-flows.store'), ['name' => 'Survey Flow'])
            ->assertRedirect();

        $this->assertDatabaseHas('whatsapp_flows', ['name' => 'Survey Flow']);
    }

    public function test_store_validates_name_required(): void
    {
        $this->actingAsTenantUser()
            ->post(route('whatsapp-flows.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_store_with_submit_action(): void
    {
        $this->actingAsTenantUser()
            ->post(route('whatsapp-flows.store'), [
                'name' => 'Lead Capture',
                'on_submit_action' => 'create_lead',
            ])
            ->assertRedirect();

        $flow = WhatsappFlow::query()->where('name', 'Lead Capture')->first();
        $this->assertSame(WhatsappFlowSubmitAction::CreateLead, $flow->on_submit_action);
    }

    public function test_update_modifies_flow(): void
    {
        $flow = WhatsappFlow::factory()->create();

        $this->actingAsTenantUser()
            ->put(route('whatsapp-flows.update', $flow), ['name' => 'Updated Name'])
            ->assertRedirect();

        $this->assertDatabaseHas('whatsapp_flows', ['id' => $flow->id, 'name' => 'Updated Name']);
    }

    public function test_destroy_soft_deletes_flow(): void
    {
        $flow = WhatsappFlow::factory()->create();

        $this->actingAsTenantUser()
            ->delete(route('whatsapp-flows.destroy', $flow))
            ->assertRedirect();

        $this->assertSoftDeleted('whatsapp_flows', ['id' => $flow->id]);
    }

    public function test_publish_sets_active_and_generates_endpoint(): void
    {
        $flow = WhatsappFlow::factory()->withFlowJson()->create([
            'draft_synced_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->post(route('whatsapp-flows.publish', $flow))
            ->assertRedirect();

        $flow->refresh();
        $this->assertSame(WhatsappFlowStatus::Active, $flow->status);
        $this->assertNotNull($flow->published_at);
        $this->assertNotNull($flow->data_exchange_endpoint);
        $this->assertStringContainsString('/v1/flow-exchange/', $flow->data_exchange_endpoint);
        $this->assertNotNull($flow->exchange_token);
    }

    public function test_publish_requires_draft_sync(): void
    {
        $flow = WhatsappFlow::factory()->withFlowJson()->create([
            'draft_synced_at' => null,
        ]);

        $this->actingAsTenantUser()
            ->post(route('whatsapp-flows.publish', $flow))
            ->assertSessionHasErrors();
    }

    public function test_archive_sets_archived_status(): void
    {
        $flow = WhatsappFlow::factory()->active()->create();

        $this->actingAsTenantUser()
            ->patch(route('whatsapp-flows.archive', $flow))
            ->assertRedirect();

        $flow->refresh();
        $this->assertSame(WhatsappFlowStatus::Archived, $flow->status);
    }

    public function test_duplicate_creates_copy(): void
    {
        $flow = WhatsappFlow::factory()->withFlowJson()->create(['name' => 'Original']);

        $this->actingAsTenantUser()
            ->post(route('whatsapp-flows.duplicate', $flow))
            ->assertRedirect();

        $this->assertDatabaseCount('whatsapp_flows', 2);
    }

    public function test_duplicate_does_not_copy_uuid_or_endpoint(): void
    {
        $flow = WhatsappFlow::factory()->active()->withFlowJson()->create();

        $this->actingAsTenantUser()
            ->post(route('whatsapp-flows.duplicate', $flow));

        $clone = WhatsappFlow::query()->where('id', '!=', $flow->id)->first();
        $this->assertNotSame($flow->uuid, $clone->uuid);
        $this->assertNull($clone->data_exchange_endpoint);
        $this->assertNull($clone->meta_flow_id);
    }

    public function test_stats_page_loads(): void
    {
        $flow = WhatsappFlow::factory()->withSubmissions(3)->create();

        $this->actingAsTenantUser()
            ->get(route('whatsapp-flows.stats', $flow))
            ->assertOk()
            ->assertSee($flow->name);
    }

    public function test_unauthenticated_access_redirects(): void
    {
        $this->get(route('whatsapp-flows.index'))->assertRedirect(route('login'));
    }
}
