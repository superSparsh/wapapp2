<?php

namespace Tests\Feature\Webhooks;

use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Models\MessageExternalIndex;
use App\Models\WhatsappLineRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WhatsappLineRegistryServiceTest extends TestCase
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

    public function test_sync_line_creates_registry_entry(): void
    {
        $service = app(WhatsappLineRegistryService::class);

        // Clear existing entries created by setUpTenant
        WhatsappLineRegistry::query()->delete();
        $this->assertSame(0, WhatsappLineRegistry::query()->count());

        $service->syncLine($this->testTenant->id, 50, '917777700001');

        $registry = WhatsappLineRegistry::query()->where('phone', '917777700001')->first();
        $this->assertNotNull($registry);
        $this->assertSame($this->testTenant->id, $registry->tenant_id);
        $this->assertSame(50, $registry->line_id);
    }

    public function test_sync_line_updates_existing_entry(): void
    {
        $service = app(WhatsappLineRegistryService::class);

        // Use a unique line_id to avoid unique constraint with setUpTenant entry
        $service->syncLine($this->testTenant->id, 60, '917777700002');

        $registry = WhatsappLineRegistry::query()->where('phone', '917777700002')->first();
        $this->assertSame(60, $registry->line_id);

        // Update the same phone with different line ID
        $service->syncLine($this->testTenant->id, 61, '917777700002');

        $registry->refresh();
        $this->assertSame(61, $registry->line_id);
        // Should still be only 1 entry (updateOrCreate)
        $this->assertSame(1, WhatsappLineRegistry::query()->where('phone', '917777700002')->count());
    }

    public function test_remove_line_deletes_registry_entry(): void
    {
        $service = app(WhatsappLineRegistryService::class);

        // Use unique line_id to avoid constraint conflict
        $service->syncLine($this->testTenant->id, 70, '917777700003');
        $this->assertNotNull(WhatsappLineRegistry::query()->where('phone', '917777700003')->first());

        $service->removeLine('917777700003');

        $this->assertNull(WhatsappLineRegistry::query()->where('phone', '917777700003')->first());
    }

    public function test_resolve_by_business_phone_returns_tenant_and_line(): void
    {
        $service = app(WhatsappLineRegistryService::class);

        // setUpTenant already synced '919999999999'
        $result = $service->resolveByBusinessPhone('919999999999');

        $this->assertNotNull($result);
        $this->assertSame($this->testTenant->id, $result['tenant']->id);
        $this->assertSame((int) $this->testLine->id, $result['line_id']);
    }

    public function test_resolve_by_business_phone_returns_null_for_unknown(): void
    {
        $service = app(WhatsappLineRegistryService::class);

        $result = $service->resolveByBusinessPhone('910000000000');

        $this->assertNull($result);
    }

    public function test_resolve_returns_null_for_null_phone(): void
    {
        $service = app(WhatsappLineRegistryService::class);

        $result = $service->resolveByBusinessPhone(null);

        $this->assertNull($result);
    }

    public function test_index_message_creates_external_index(): void
    {
        $service = app(WhatsappLineRegistryService::class);

        $service->indexMessage($this->testTenant->id, 'wamid.EXT-001', 42);

        $index = MessageExternalIndex::query()->where('external_message_id', 'wamid.EXT-001')->first();
        $this->assertNotNull($index);
        $this->assertSame($this->testTenant->id, $index->tenant_id);
        $this->assertSame(42, $index->message_id);
    }

    public function test_index_message_skips_empty_external_id(): void
    {
        $service = app(WhatsappLineRegistryService::class);

        $service->indexMessage($this->testTenant->id, '', 42);

        $this->assertSame(0, MessageExternalIndex::query()->count());
    }

    public function test_resolve_tenant_by_external_message_id(): void
    {
        $service = app(WhatsappLineRegistryService::class);

        $service->indexMessage($this->testTenant->id, 'wamid.RESOLVE-001', 99);

        $tenant = $service->resolveTenantByExternalMessageId('wamid.RESOLVE-001');

        $this->assertNotNull($tenant);
        $this->assertSame($this->testTenant->id, $tenant->id);
    }

    public function test_resolve_tenant_by_external_message_id_returns_null_for_unknown(): void
    {
        $service = app(WhatsappLineRegistryService::class);

        $tenant = $service->resolveTenantByExternalMessageId('wamid.NONEXISTENT');

        $this->assertNull($tenant);
    }
}
