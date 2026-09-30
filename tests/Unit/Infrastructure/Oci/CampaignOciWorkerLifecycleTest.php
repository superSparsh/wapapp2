<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Oci;

use App\Domains\Infrastructure\Oci\CampaignOciWorkerLifecycle;
use App\Domains\Infrastructure\Oci\Contracts\OciContainerInstanceClient;
use App\Domains\Infrastructure\Oci\Jobs\EnsureOciCampaignWorkerJob;
use App\Domains\Infrastructure\Oci\Jobs\TeardownOciCampaignWorkerJob;
use App\Domains\Infrastructure\Oci\LogOciContainerInstanceClient;
use App\Enums\TenantStatus;
use App\Models\Campaign;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;
use Tests\TestCase;

class CampaignOciWorkerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private CampaignOciWorkerLifecycle $lifecycle;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        // Avoid CreateDatabase / MigrateDatabase pipelines — only need tenant() id.
        Event::fake([TenantCreated::class, TenantDeleted::class]);
        config([
            'oci-workers.enabled' => true,
            'oci-workers.ephemeral.enabled' => true,
            'oci-workers.ephemeral.driver' => 'log',
            'oci-workers.ephemeral.grace_seconds' => 60,
            'oci-workers.ephemeral.provisioning_queue' => 'provisioning',
            'tenancy.bootstrappers' => [],
        ]);

        $this->tenantId = 'oci-'.Str::lower(Str::random(8));
        $tenant = Tenant::query()->create([
            'id' => $this->tenantId,
            'name' => 'OCI Test Tenant',
            'status' => TenantStatus::Active,
        ]);
        tenancy()->initialize($tenant);

        $this->app->bind(OciContainerInstanceClient::class, LogOciContainerInstanceClient::class);
        $this->lifecycle = app(CampaignOciWorkerLifecycle::class);
        $this->lifecycle->storeActiveCampaignIds([]);
        $this->lifecycle->forgetInstanceOcid();
        $this->lifecycle->forgetCampaignLoad();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function campaign(int $id, int $recipients = 0): Campaign
    {
        $campaign = new Campaign;
        $campaign->id = $id;
        $campaign->total_recipients = $recipients;

        return $campaign;
    }

    private function initializeTenant(string $id): Tenant
    {
        $tenant = Tenant::query()->find($id) ?? Tenant::query()->create([
            'id' => $id,
            'name' => $id,
            'status' => TenantStatus::Active,
        ]);
        tenancy()->initialize($tenant);

        return $tenant;
    }

    public function test_disabled_when_flag_off(): void
    {
        config(['oci-workers.ephemeral.enabled' => false]);

        $this->lifecycle->onCampaignStarted($this->campaign(1));

        Queue::assertNotPushed(EnsureOciCampaignWorkerJob::class);
    }

    public function test_first_campaign_dispatches_ensure_job(): void
    {
        $this->lifecycle->onCampaignStarted($this->campaign(42));

        Queue::assertPushed(EnsureOciCampaignWorkerJob::class);
        $this->assertSame([
            $this->tenantId.':42' => true,
        ], $this->lifecycle->activeCampaignIds());
    }

    public function test_last_campaign_finished_dispatches_teardown_job(): void
    {
        $campaign = $this->campaign(7);

        $this->lifecycle->onCampaignStarted($campaign);
        Queue::fake();

        $this->lifecycle->onCampaignFinished($campaign);

        Queue::assertPushed(TeardownOciCampaignWorkerJob::class);
        $this->assertSame([], $this->lifecycle->activeCampaignIds());
    }

    public function test_teardown_skipped_while_other_campaigns_active(): void
    {
        $a = $this->campaign(1);
        $b = $this->campaign(2);

        $this->lifecycle->onCampaignStarted($a);
        $this->lifecycle->onCampaignStarted($b);
        Queue::fake();

        $this->lifecycle->onCampaignFinished($a);

        Queue::assertNotPushed(TeardownOciCampaignWorkerJob::class);
        $this->assertSame([
            $this->tenantId.':2' => true,
        ], $this->lifecycle->activeCampaignIds());
    }

    public function test_same_numeric_id_different_tenants_do_not_collide(): void
    {
        $local = $this->campaign(5);
        $this->lifecycle->onCampaignStarted($local);

        $otherId = 'other-'.Str::lower(Str::random(6));
        $this->initializeTenant($otherId);

        $remote = $this->campaign(5);
        $this->lifecycle->onCampaignStarted($remote);
        Queue::fake();

        $this->lifecycle->onCampaignFinished($remote);

        Queue::assertNotPushed(TeardownOciCampaignWorkerJob::class);
        $this->assertSame([
            $this->tenantId.':5' => true,
        ], $this->lifecycle->activeCampaignIds());

        $this->initializeTenant($this->tenantId);
        $this->lifecycle->onCampaignFinished($local);

        $this->assertSame([], $this->lifecycle->activeCampaignIds());
        Queue::assertPushed(TeardownOciCampaignWorkerJob::class);
    }

    public function test_ensure_worker_creates_and_stores_ocid(): void
    {
        $this->lifecycle->storeActiveCampaignIds([
            $this->tenantId.':99' => true,
        ]);

        $this->lifecycle->ensureWorker(app(OciContainerInstanceClient::class));

        $ocid = $this->lifecycle->instanceOcid();
        $this->assertIsString($ocid);
        $this->assertStringStartsWith('ocid1.containerinstance.', $ocid);
    }

    public function test_resolve_shape_picks_tier_by_recipients(): void
    {
        $small = $this->lifecycle->resolveShape(1000);
        $this->assertSame(1.0, $small['ocpus']);
        $this->assertSame(4.0, $small['memory_in_gbs']);
        $this->assertSame(2, $small['campaign_max_processes']);

        $medium = $this->lifecycle->resolveShape(20000);
        $this->assertSame(2.0, $medium['ocpus']);
        $this->assertSame(8.0, $medium['memory_in_gbs']);
        $this->assertSame(4, $medium['campaign_max_processes']);

        $large = $this->lifecycle->resolveShape(100000);
        $this->assertSame(4.0, $large['ocpus']);
        $this->assertSame(16.0, $large['memory_in_gbs']);
        $this->assertSame(8, $large['campaign_max_processes']);
    }

    public function test_campaign_started_stores_recipient_load(): void
    {
        $this->lifecycle->onCampaignStarted($this->campaign(55, 25000));

        $this->assertSame([
            $this->tenantId.':55' => 25000,
        ], $this->lifecycle->campaignLoad());
        $this->assertSame(25000, $this->lifecycle->maxRecipientDemand());
        $this->assertSame(2.0, $this->lifecycle->resolveShape()['ocpus']);
    }

    public function test_teardown_worker_deletes_when_idle(): void
    {
        $this->lifecycle->storeActiveCampaignIds([]);
        $this->lifecycle->storeInstanceOcid('ocid1.containerinstance.oc1.test.deadbeef');
        $this->lifecycle->storeInstanceStartedAt(now()->subMinutes(12)->toIso8601String());

        $this->lifecycle->teardownWorker(app(OciContainerInstanceClient::class));

        $this->assertNull($this->lifecycle->instanceOcid());
        $session = $this->lifecycle->lastSession();
        $this->assertIsArray($session);
        $this->assertGreaterThan(0, $session['active_seconds']);
    }

    public function test_teardown_skipped_when_any_tenant_still_active(): void
    {
        $this->lifecycle->storeActiveCampaignIds([
            'acme:1' => true,
        ]);
        $this->lifecycle->storeInstanceOcid('ocid1.containerinstance.oc1.test.keep');

        $this->lifecycle->teardownWorker(app(OciContainerInstanceClient::class));

        $this->assertSame('ocid1.containerinstance.oc1.test.keep', $this->lifecycle->instanceOcid());
    }

    public function test_force_destroy_clears_state_and_records_session(): void
    {
        $this->lifecycle->storeActiveCampaignIds([
            $this->tenantId.':1' => true,
        ]);
        $this->lifecycle->storeCampaignLoad([
            $this->tenantId.':1' => 100,
        ]);
        $this->lifecycle->storeInstanceOcid('ocid1.containerinstance.oc1.test.force');
        $this->lifecycle->storeInstanceStartedAt(now()->subHour()->toIso8601String());

        $result = $this->lifecycle->forceDestroy(app(OciContainerInstanceClient::class));

        $this->assertContains('ocid1.containerinstance.oc1.test.force', $result['deleted']);
        $this->assertNull($this->lifecycle->instanceOcid());
        $this->assertSame([], $this->lifecycle->activeCampaignIds());
        $this->assertIsArray($result['session']);
        $this->assertGreaterThanOrEqual(3500, $result['session']['active_seconds']);
    }

    public function test_finish_clears_legacy_bare_id_aliases_for_same_campaign(): void
    {
        $campaign = $this->campaign(12);

        $this->lifecycle->storeActiveCampaignIds([
            '12' => true,
            '_:12' => true,
            $this->tenantId.':12' => true,
            'beta:12' => true,
        ]);

        Queue::fake();
        $this->lifecycle->onCampaignFinished($campaign);

        $this->assertSame([
            'beta:12' => true,
        ], $this->lifecycle->activeCampaignIds());
        Queue::assertNotPushed(TeardownOciCampaignWorkerJob::class);
    }

    public function test_skips_when_tenant_context_missing(): void
    {
        tenancy()->end();

        $this->lifecycle->onCampaignStarted($this->campaign(9));

        Queue::assertNotPushed(EnsureOciCampaignWorkerJob::class);
        $this->assertSame([], $this->lifecycle->activeCampaignIds());
    }

    public function test_status_snapshot_includes_last_session_duration(): void
    {
        $this->lifecycle->storeInstanceOcid('ocid1.containerinstance.oc1.test.live');
        $this->lifecycle->storeInstanceStartedAt(now()->subMinutes(5)->toIso8601String());
        $this->lifecycle->storeLastSession([
            'started_at' => now()->subHour()->toIso8601String(),
            'ended_at' => now()->subMinutes(30)->toIso8601String(),
            'active_seconds' => 1800,
        ]);

        $snap = $this->lifecycle->statusSnapshot();

        $this->assertNotNull($snap['ocid']);
        $this->assertNotNull($snap['active_for_humans']);
        $this->assertSame('30m 0s', $snap['last_session']['active_for_humans']);
    }
}
