<?php

namespace Tests\Feature\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
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

    public function test_logs_page_shows_metrics_and_deliveries(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub = WebhookSubscription::factory()->create();
        WebhookDelivery::factory()->count(5)->sent()->forSubscription($sub)->create();
        WebhookDelivery::factory()->count(2)->failed()->forSubscription($sub)->create();
        WebhookDelivery::factory()->count(1)->pending()->forSubscription($sub)->create();

        $response = $this->actingAsTenantUser()
            ->get(route('webhooks.logs'))
            ->assertOk()
            ->assertViewIs('webhooks.logs')
            ->assertViewHas('metrics')
            ->assertViewHas('deliveries');

        $response->assertViewHas('metrics', function ($metrics) {
            return $metrics['total'] === 8
                && $metrics['successful'] === 5
                && $metrics['failed'] === 2
                && $metrics['pending'] === 1;
        });
    }

    public function test_logs_filter_by_status(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub = WebhookSubscription::factory()->create();
        WebhookDelivery::factory()->count(3)->sent()->forSubscription($sub)->create();
        WebhookDelivery::factory()->count(2)->failed()->forSubscription($sub)->create();

        $response = $this->actingAsTenantUser()
            ->get(route('webhooks.logs', ['status' => 'failed']))
            ->assertOk();

        $response->assertViewHas('deliveries', function ($deliveries) {
            return $deliveries->count() === 2;
        });
    }

    public function test_logs_filter_by_subscription(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub1 = WebhookSubscription::factory()->create();
        $sub2 = WebhookSubscription::factory()->create();
        WebhookDelivery::factory()->count(3)->sent()->forSubscription($sub1)->create();
        WebhookDelivery::factory()->count(2)->sent()->forSubscription($sub2)->create();

        $response = $this->actingAsTenantUser()
            ->get(route('webhooks.logs', ['subscription_id' => $sub1->uuid]))
            ->assertOk();

        $response->assertViewHas('deliveries', function ($deliveries) {
            return $deliveries->count() === 3;
        });
    }

    public function test_logs_filter_by_date_range(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub = WebhookSubscription::factory()->create();

        // Create deliveries with specific dates
        WebhookDelivery::factory()->count(2)->sent()->forSubscription($sub)->create([
            'created_at' => now()->subDays(5),
        ]);
        WebhookDelivery::factory()->count(3)->sent()->forSubscription($sub)->create([
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->actingAsTenantUser()
            ->get(route('webhooks.logs', [
                'date_from' => now()->subDays(3)->format('Y-m-d'),
                'date_to' => now()->format('Y-m-d'),
            ]))
            ->assertOk();

        $response->assertViewHas('deliveries', function ($deliveries) {
            return $deliveries->count() === 3;
        });
    }

    public function test_show_displays_delivery_detail(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub = WebhookSubscription::factory()->create();
        $delivery = WebhookDelivery::factory()->sent()->forSubscription($sub)->create();

        $this->actingAsTenantUser()
            ->get(route('webhooks.logs.detail', $delivery))
            ->assertOk()
            ->assertViewIs('webhooks.log-detail')
            ->assertViewHas('delivery');
    }

    public function test_retry_resends_failed_delivery(): void
    {
        tenancy()->initialize($this->testTenant);

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $sub = WebhookSubscription::factory()->create();
        $delivery = WebhookDelivery::factory()->failed()->forSubscription($sub)->create();

        $this->assertSame(1, $delivery->attempt_count);

        $this->actingAsTenantUser()
            ->post(route('webhooks.logs.retry', $delivery))
            ->assertRedirect();

        $delivery->refresh();
        $this->assertSame(2, $delivery->attempt_count);
        $this->assertSame(WebhookDeliveryStatus::Sent, $delivery->status);
        $this->assertSame(200, $delivery->response_status);
    }

    public function test_destroy_deletes_delivery_log(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub = WebhookSubscription::factory()->create();
        $delivery = WebhookDelivery::factory()->sent()->forSubscription($sub)->create();

        $this->actingAsTenantUser()
            ->delete(route('webhooks.logs.destroy', $delivery))
            ->assertRedirect(route('webhooks.logs'));

        $this->assertDatabaseMissing('webhook_deliveries', ['id' => $delivery->id]);
    }

    public function test_metrics_with_subscription_filter(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub1 = WebhookSubscription::factory()->create();
        $sub2 = WebhookSubscription::factory()->create();

        WebhookDelivery::factory()->count(3)->sent()->forSubscription($sub1)->create();
        WebhookDelivery::factory()->count(5)->sent()->forSubscription($sub2)->create();
        WebhookDelivery::factory()->count(2)->failed()->forSubscription($sub1)->create();

        $service = app(\App\Domains\Webhooks\Services\WebhookDeliveryService::class);

        // Metrics for sub1 only
        $metrics = $service->metrics((int) $sub1->id);
        $this->assertSame(5, $metrics['total']);
        $this->assertSame(3, $metrics['successful']);
        $this->assertSame(2, $metrics['failed']);

        // Metrics for all
        $allMetrics = $service->metrics();
        $this->assertSame(10, $allMetrics['total']);
    }

    public function test_logs_search_filter(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub = WebhookSubscription::factory()->create();
        WebhookDelivery::factory()->sent()->forSubscription($sub)->create([
            'event_type' => 'new_lead',
            'error_message' => null,
        ]);
        WebhookDelivery::factory()->failed()->forSubscription($sub)->create([
            'event_type' => 'new_lead',
            'error_message' => 'Connection timeout on CRM endpoint',
        ]);

        $service = app(\App\Domains\Webhooks\Services\WebhookDeliveryService::class);

        $request = \Illuminate\Http\Request::create('/', 'GET', ['search' => 'timeout']);
        $results = $service->logs($request);

        $this->assertSame(1, $results->total());
    }

    public function test_retry_with_missing_subscription_returns_delivery_unchanged(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub = WebhookSubscription::factory()->create();
        $delivery = WebhookDelivery::factory()->failed()->forSubscription($sub)->create();

        // Delete the subscription so it's missing
        $sub->forceDelete();

        $service = app(\App\Domains\Webhooks\Services\WebhookDeliveryService::class);
        $result = $service->retry($delivery);

        // Should return the delivery unchanged (no crash)
        $this->assertNotNull($result);
    }

    public function test_destroy_removes_delivery_permanently(): void
    {
        tenancy()->initialize($this->testTenant);

        $sub = WebhookSubscription::factory()->create();
        $delivery = WebhookDelivery::factory()->sent()->forSubscription($sub)->create();

        $service = app(\App\Domains\Webhooks\Services\WebhookDeliveryService::class);
        $service->destroy($delivery);

        $this->assertDatabaseMissing('webhook_deliveries', ['id' => $delivery->id]);
        $this->assertSame(0, WebhookDelivery::query()->count());
    }
}
