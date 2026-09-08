<?php

declare(strict_types=1);

namespace Tests\Feature\ThirdParty;

use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Domains\ThirdParty\Models\ShopifyIntegration;
use App\Domains\ThirdParty\Services\ShopifyWebhookIngestService;
use App\Domains\ThirdParty\Services\ShopifyWebhookProcessorService;
use App\Models\ShopifyDomainRegistry;
use App\Models\ShopifyWebhookEvent;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ShopifyWebhookTest extends TestCase
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

    public function test_webhook_rejects_invalid_hmac(): void
    {
        config(['services.shopify.webhook_secret' => 'secret']);

        $this->postJson('/api/v1/webhooks/shopify/orders/create', ['id' => 1], [
            'X-Shopify-Hmac-Sha256' => 'bad',
            'X-Shopify-Shop-Domain' => 'demo.myshopify.com',
        ])->assertStatus(401);
    }

    public function test_webhook_enqueues_when_hmac_and_domain_valid(): void
    {
        config(['services.shopify.webhook_secret' => 'secret']);

        $body = json_encode(['id' => 99, 'phone' => '+919999999999'], JSON_THROW_ON_ERROR);
        $hmac = base64_encode(hash_hmac('sha256', $body, 'secret', true));

        ShopifyDomainRegistry::query()->create([
            'shop_domain' => 'demo.myshopify.com',
            'tenant_id' => (string) $this->testTenant->id,
            'user_id' => $this->testUser->id,
        ]);

        $this->call(
            'POST',
            '/api/v1/webhooks/shopify/orders/create',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
                'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'demo.myshopify.com',
            ],
            $body,
        )->assertOk();

        $this->assertDatabaseHas('shopify_webhook_events', [
            'tenant_id' => (string) $this->testTenant->id,
            'topic' => 'orders_create',
            'status' => 'pending',
        ], config('tenancy.database.central_connection'));
    }

    public function test_processor_sends_template_for_enabled_order_scope(): void
    {
        config([
            'services.shopify.webhook_secret' => 'secret',
            'whatsapp.alibaba.access_key_id' => 'test-key',
            'whatsapp.alibaba.access_key_secret' => 'test-secret',
            'whatsapp.alibaba.endpoint' => 'cams.test.local',
            'whatsapp.outbound_driver' => 'local',
        ]);

        Http::fake([
            'https://cams.test.local/*' => Http::response(['MessageId' => 'wamid.1'], 200),
        ]);

        WhatsappLine::factory()->connected()->defaultLine()->create();
        $template = Template::factory()->create(['code' => 'order_created']);

        ShopifyIntegration::query()->create([
            'user_id' => $this->testUser->id,
            'status' => IntegrationStatus::Enabled,
            'settings' => [
                'shopifydomainurl' => 'https://demo.myshopify.com',
                'access_scope_check' => [
                    ['key' => 'orders_create', 'value' => 'yes'],
                ],
                'template_selected' => [
                    ['key' => 'orders_create', 'value' => (string) $template->id],
                ],
            ],
        ]);

        $event = ShopifyWebhookEvent::query()->create([
            'tenant_id' => (string) $this->testTenant->id,
            'shop_domain' => 'demo.myshopify.com',
            'topic' => 'orders_create',
            'payload' => [
                'phone' => '919876543210',
                'shop_domain' => 'demo.myshopify.com',
            ],
            'status' => 'pending',
        ]);

        app(ShopifyWebhookProcessorService::class)->processEvent($event->fresh());

        $event->refresh();
        $this->assertSame('processed', $event->status);
    }

    public function test_domain_save_registers_central_lookup(): void
    {
        $this->actingAsTenantUser()
            ->postJson(route('integration.shopify.domain.store'), [
                'domainurl' => 'https://acme.myshopify.com',
            ])
            ->assertOk();

        $this->assertSame(
            (string) $this->testTenant->id,
            app(ShopifyWebhookIngestService::class)->resolveTenantId('acme.myshopify.com'),
        );
    }
}
