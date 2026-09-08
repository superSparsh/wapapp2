<?php

namespace Tests\Feature\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookSubscriptionStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WebhookSubscriptionTest extends TestCase
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
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_index_page_shows_subscriptions(): void
    {
        tenancy()->initialize($this->testTenant);

        WebhookSubscription::factory()->count(3)->create();

        $this->actingAsTenantUser()
            ->get(route('webhooks.index'))
            ->assertOk()
            ->assertViewIs('webhooks.index')
            ->assertViewHas('subscriptions')
            ->assertViewHas('mailLists');
    }

    public function test_store_creates_subscription_with_secret_key(): void
    {
        tenancy()->initialize($this->testTenant);

        $this->actingAsTenantUser()
            ->post(route('webhooks.store'), [
                'url' => 'https://example.com/webhook',
                'description' => 'CRM Integration',
                'events' => ['new_lead'],
                'status' => 'active',
            ])
            ->assertRedirect(route('webhooks.index'));

        $sub = WebhookSubscription::query()->first();
        $this->assertNotNull($sub);
        $this->assertSame('https://example.com/webhook', $sub->url);
        $this->assertSame('CRM Integration', $sub->description);
        $this->assertSame(WebhookSubscriptionStatus::Active, $sub->status);
        $this->assertNotEmpty($sub->secret_key);
        $this->assertSame(32, strlen($sub->secret_key));
    }

    public function test_store_validates_url_format(): void
    {
        tenancy()->initialize($this->testTenant);

        $this->actingAsTenantUser()
            ->post(route('webhooks.store'), [
                'url' => 'not-a-valid-url',
                'description' => 'Test',
                'events' => ['new_lead'],
                'status' => 'active',
            ])
            ->assertSessionHasErrors('url');
    }

    public function test_store_validates_required_fields(): void
    {
        tenancy()->initialize($this->testTenant);

        $this->actingAsTenantUser()
            ->post(route('webhooks.store'), [])
            ->assertSessionHasErrors(['url', 'description', 'events', 'status']);
    }

    public function test_update_modifies_subscription(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub = WebhookSubscription::factory()->create();

        $this->actingAsTenantUser()
            ->put(route('webhooks.update', $sub), [
                'url' => 'https://new-url.com/hook',
                'description' => 'Updated Description',
                'events' => ['new_lead'],
                'status' => 'inactive',
            ])
            ->assertRedirect(route('webhooks.index'));

        $sub->refresh();
        $this->assertSame('https://new-url.com/hook', $sub->url);
        $this->assertSame('Updated Description', $sub->description);
        $this->assertSame(WebhookSubscriptionStatus::Inactive, $sub->status);
    }

    public function test_destroy_deletes_subscription_and_deliveries(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub = WebhookSubscription::factory()->create();
        WebhookDelivery::factory()->count(3)->forSubscription($sub)->create();

        $this->assertSame(3, WebhookDelivery::query()->count());

        $this->actingAsTenantUser()
            ->delete(route('webhooks.destroy', $sub))
            ->assertRedirect(route('webhooks.index'));

        // Soft delete — force delete check
        $this->assertSoftDeleted('webhook_subscriptions', ['id' => $sub->id]);
    }

    public function test_toggle_status_flips_active_inactive(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub = WebhookSubscription::factory()->active()->create();
        $this->assertSame(WebhookSubscriptionStatus::Active, $sub->status);

        $response = $this->actingAsTenantUser()
            ->post(route('webhooks.toggle', $sub))
            ->assertOk()
            ->assertJson(['status' => 'inactive', 'label' => 'Inactive']);

        $sub->refresh();
        $this->assertSame(WebhookSubscriptionStatus::Inactive, $sub->status);

        // Toggle back
        $this->actingAsTenantUser()
            ->post(route('webhooks.toggle', $sub))
            ->assertOk()
            ->assertJson(['status' => 'active', 'label' => 'Active']);

        $sub->refresh();
        $this->assertSame(WebhookSubscriptionStatus::Active, $sub->status);
    }

    public function test_regenerate_secret_generates_new_key(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub = WebhookSubscription::factory()->create();
        $oldKey = $sub->secret_key;

        $response = $this->actingAsTenantUser()
            ->post(route('webhooks.regenerate-secret', $sub))
            ->assertOk()
            ->assertJsonStructure(['secret_key']);

        $sub->refresh();
        $this->assertNotSame($oldKey, $sub->secret_key);
        $this->assertSame(32, strlen($sub->secret_key));
    }

    public function test_test_delivery_sends_sample_payload(): void
    {
        tenancy()->initialize($this->testTenant);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $sub = WebhookSubscription::factory()->active()->create();

        $response = $this->actingAsTenantUser()
            ->post(route('webhooks.test', $sub))
            ->assertOk()
            ->assertJsonStructure(['status', 'response_status', 'duration_ms']);

        $delivery = WebhookDelivery::query()->first();
        $this->assertNotNull($delivery);
        $this->assertSame(WebhookDeliveryStatus::Sent, $delivery->status);
        $this->assertSame(200, $delivery->response_status);
    }

    public function test_test_delivery_records_failure_on_error(): void
    {
        tenancy()->initialize($this->testTenant);

        Http::fake(['*' => Http::response('Server Error', 500)]);

        $sub = WebhookSubscription::factory()->active()->create();

        $this->actingAsTenantUser()
            ->post(route('webhooks.test', $sub))
            ->assertOk()
            ->assertJson(['status' => 'failed']);

        $delivery = WebhookDelivery::query()->first();
        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->status);
        $this->assertSame(500, $delivery->response_status);
    }
}
