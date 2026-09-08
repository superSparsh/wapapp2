<?php

declare(strict_types=1);

namespace Tests\Feature\ThirdParty;

use App\Domains\ThirdParty\Models\ShopifyIntegration;
use App\Domains\ThirdParty\Models\ShopifySendData;
use App\Domains\ThirdParty\Services\ShopifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ShopifyIntegrationTest extends TestCase
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

    // ─── Page accessibility ───────────────────────────────────────────────────

    public function test_shopify_index_requires_auth(): void
    {
        $this->get(route('integration.index'))->assertRedirect();
    }

    public function test_shopify_index_page_loads(): void
    {
        $this->actingAsTenantUser()
            ->get(route('integration.index'))
            ->assertOk()
            ->assertViewIs('integration.index');
    }

    public function test_shopify_scopes_page_loads(): void
    {
        $this->actingAsTenantUser()
            ->get(route('integration.shopify.scopes'))
            ->assertOk()
            ->assertViewIs('integration.shopify-scopes');
    }

    // ─── Domain URL ───────────────────────────────────────────────────────────

    public function test_store_domain_url_validates_url_format(): void
    {
        $this->actingAsTenantUser()
            ->post(route('integration.shopify.domain.store'), ['domainurl' => 'not-a-url'])
            ->assertSessionHasErrors(['domainurl']);
    }

    public function test_store_domain_url_requires_url(): void
    {
        $this->actingAsTenantUser()
            ->post(route('integration.shopify.domain.store'), [])
            ->assertSessionHasErrors(['domainurl']);
    }

    public function test_store_domain_url_saves_to_database(): void
    {
        $userId = (int) $this->testUser->id;

        $this->actingAsTenantUser()
            ->postJson(route('integration.shopify.domain.store'), [
                'domainurl' => 'https://mystore.myshopify.com',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $integration = ShopifyIntegration::query()->where('user_id', $userId)->first();
        $this->assertNotNull($integration);
        $this->assertSame('https://mystore.myshopify.com', $integration->settings['shopifydomainurl'] ?? null);
    }

    // ─── Get Domain ───────────────────────────────────────────────────────────

    public function test_get_domain_returns_not_found_when_no_domain(): void
    {
        $this->actingAsTenantUser()
            ->getJson(route('integration.shopify.domain.get'))
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    public function test_get_domain_returns_domain_when_set(): void
    {
        $userId = (int) $this->testUser->id;
        app(ShopifyService::class)->saveDomainUrl($userId, 'https://store.myshopify.com');

        $this->actingAsTenantUser()
            ->getJson(route('integration.shopify.domain.get'))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function test_save_scopes_updates_access_scope_check(): void
    {
        $this->actingAsTenantUser()
            ->post(route('integration.shopify.scopes.save'), [
                'access_scope_check' => true,
                'products_create'    => true,
            ])
            ->assertRedirect(route('integration.shopify.scopes'));

        $integration = ShopifyIntegration::query()->where('user_id', $this->testUser->id)->first();
        $this->assertNotNull($integration);
    }

    public function test_get_scopes_returns_json(): void
    {
        $this->actingAsTenantUser()
            ->getJson(route('integration.shopify.scopes.get'))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    // ─── Send Data ────────────────────────────────────────────────────────────

    public function test_get_send_data_returns_paginated_json(): void
    {
        $userId = (int) $this->testUser->id;

        ShopifySendData::query()->create([
            'user_id'         => $userId,
            'event_type'      => 'orders/paid',
            'whatsapp_number' => '+911234567890',
            'status'          => 'sent',
        ]);

        $this->actingAsTenantUser()
            ->getJson(route('integration.shopify.data'))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    // ─── Service layer ────────────────────────────────────────────────────────

    public function test_shopify_service_find_or_create_creates_integration(): void
    {
        $userId  = (int) $this->testUser->id;
        $service = app(ShopifyService::class);

        $integration = $service->findOrCreate($userId);

        $this->assertInstanceOf(ShopifyIntegration::class, $integration);
        $this->assertSame($userId, $integration->user_id);
    }

    public function test_shopify_service_find_or_create_idempotent(): void
    {
        $userId  = (int) $this->testUser->id;
        $service = app(ShopifyService::class);

        $a = $service->findOrCreate($userId);
        $b = $service->findOrCreate($userId);

        $this->assertSame($a->id, $b->id);
    }

    public function test_shopify_service_save_domain_url(): void
    {
        $userId  = (int) $this->testUser->id;
        $service = app(ShopifyService::class);

        $integration = $service->saveDomainUrl($userId, 'https://test.myshopify.com');

        $this->assertSame('https://test.myshopify.com', $integration->settings['shopifydomainurl']);
    }

    public function test_shopify_service_send_data_paginates(): void
    {
        $userId = (int) $this->testUser->id;
        ShopifySendData::factory()->count(5)->create(['user_id' => $userId]);

        $result = app(ShopifyService::class)->sendData($userId, 3);

        $this->assertSame(3, $result->perPage());
        $this->assertSame(5, $result->total());
    }
}
