<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Oci;

use App\Domains\Infrastructure\Oci\CampaignOciWorkerLifecycle;
use App\Domains\Infrastructure\Oci\Contracts\OciContainerInstanceClient;
use App\Domains\Infrastructure\Oci\Jobs\EnsureOciCampaignWorkerJob;
use App\Domains\Infrastructure\Oci\Jobs\TeardownOciCampaignWorkerJob;
use App\Domains\Infrastructure\Oci\LogOciContainerInstanceClient;
use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignOciWorkerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private CampaignOciWorkerLifecycle $lifecycle;

    private const TENANT_A = 'tenant-acme';

    private const TENANT_B = 'tenant-beta';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        config([
            'oci-workers.enabled' => true,
            'oci-workers.ephemeral.enabled' => true,
            'oci-workers.ephemeral.driver' => 'log',
            'oci-workers.ephemeral.grace_seconds' => 60,
            'oci-workers.ephemeral.provisioning_queue' => 'provisioning',
        ]);

        $this->app->bind(OciContainerInstanceClient::class, LogOciContainerInstanceClient::class);
        $this->lifecycle = app(CampaignOciWorkerLifecycle::class);
        $this->lifecycle->storeActiveCampaignIds([]);
        $this->lifecycle->forgetInstanceOcid();
        $this->lifecycle->forgetCampaignLoad();
    }

    private function ref(string $tenantId, int $campaignId): string
    {
        return $tenantId.':'.$campaignId;
    }

    private function campaign(int $id): Campaign
    {
        $campaign = new Campaign;
        $campaign->id = $id;

        return $campaign;
    }

    public function test_disabled_when_flag_off(): void
    {
        config(['oci-workers.ephemeral.enabled' => false]);

        $this->lifecycle->onCampaignStarted($this->campaign(1), self::TENANT_A);

        Queue::assertNotPushed(EnsureOciCampaignWorkerJob::class);
    }

    public function test_first_campaign_dispatches_ensure_job(): void
    {
        $this->lifecycle->onCampaignStarted($this->campaign(42), self::TENANT_A);

        Queue::assertPushed(EnsureOciCampaignWorkerJob::class);
        $this->assertSame([
            $this->ref(self::TENANT_A, 42) => true,
        ], $this->lifecycle->activeCampaignIds());
    }

    public function test_last_campaign_finished_dispatches_teardown_job(): void
    {
        $campaign = $this->campaign(7);

        $this->lifecycle->onCampaignStarted($campaign, self::TENANT_A);
        Queue::fake();

        $this->lifecycle->onCampaignFinished($campaign, self::TENANT_A);

        Queue::assertPushed(TeardownOciCampaignWorkerJob::class);
        $this->assertSame([], $this->lifecycle->activeCampaignIds());
    }

    public function test_teardown_skipped_while_other_campaigns_active(): void
    {
        $this->lifecycle->onCampaignStarted($this->campaign(1), self::TENANT_A);
        $this->lifecycle->onCampaignStarted($this->campaign(2), self::TENANT_A);
        Queue::fake();

        $this->lifecycle->onCampaignFinished($this->campaign(1), self::TENANT_A);

        Queue::assertNotPushed(TeardownOciCampaignWorkerJob::class);
        $this->assertSame([
            $this->ref(self::TENANT_A, 2) => true,
        ], $this->lifecycle->activeCampaignIds());
    }

    public function test_same_numeric_id_different_tenants_do_not_collide(): void
    {
        $this->lifecycle->onCampaignStarted($this->campaign(5), self::TENANT_A);
        $this->lifecycle->onCampaignStarted($this->campaign(5), self::TENANT_B);
        Queue::fake();

        $this->lifecycle->onCampaignFinished($this->campaign(5), self::TENANT_A);

        Queue::assertNotPushed(TeardownOciCampaignWorkerJob::class);
        $this->assertSame([
            $this->ref(self::TENANT_B, 5) => true,
        ], $this->lifecycle->activeCampaignIds());
    }

    public function test_ensure_worker_creates_and_stores_ocid(): void
    {
        $this->lifecycle->storeActiveCampaignIds([
            $this->ref(self::TENANT_A, 99) => true,
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
        $campaign = $this->campaign(55);
        $campaign->total_recipients = 25000;

        $this->lifecycle->onCampaignStarted($campaign, self::TENANT_A);

        $this->assertSame([
            $this->ref(self::TENANT_A, 55) => 25000,
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

    public function test_force_destroy_clears_state_and_records_session(): void
    {
        $this->lifecycle->storeActiveCampaignIds([
            $this->ref(self::TENANT_A, 1) => true,
        ]);
        $this->lifecycle->storeCampaignLoad([
            $this->ref(self::TENANT_A, 1) => 100,
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

    public function test_finish_clears_legacy_bare_id_without_touching_other_tenants(): void
    {
        $this->lifecycle->storeActiveCampaignIds([
            '12' => true,
            'acme:12' => true,
            $this->ref(self::TENANT_A, 12) => true,
        ]);

        Queue::fake();
        $this->lifecycle->onCampaignFinished($this->campaign(12), self::TENANT_A);

        $this->assertSame([
            'acme:12' => true,
        ], $this->lifecycle->activeCampaignIds());
        Queue::assertNotPushed(TeardownOciCampaignWorkerJob::class);
    }

    public function test_underscore_and_uuid_refs_are_dropped(): void
    {
        $this->lifecycle->storeActiveCampaignIds([
            '_:12' => true,
            '12121212-1212-1212-1212-121212121212' => true,
            $this->ref(self::TENANT_A, 12) => true,
        ]);

        $this->assertSame([
            $this->ref(self::TENANT_A, 12) => true,
        ], $this->lifecycle->activeCampaignIds());
    }

    public function test_start_without_tenant_context_is_skipped(): void
    {
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
