<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Oci;

use App\Domains\Infrastructure\Oci\CampaignOciWorkerLifecycle;
use App\Domains\Infrastructure\Oci\Contracts\OciContainerInstanceClient;
use App\Domains\Infrastructure\Oci\Jobs\EnsureOciCampaignWorkerJob;
use App\Domains\Infrastructure\Oci\Jobs\ReleaseOciCampaignWorkerRefJob;
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

    private function campaign(int $id, int $workerRef, int $recipients = 0): Campaign
    {
        $campaign = new Campaign;
        $campaign->id = $id;
        $campaign->worker_ref = $workerRef;
        $campaign->total_recipients = $recipients;

        return $campaign;
    }

    public function test_disabled_when_flag_off(): void
    {
        config(['oci-workers.ephemeral.enabled' => false]);

        $this->lifecycle->onCampaignStarted($this->campaign(1, 100));

        Queue::assertNotPushed(EnsureOciCampaignWorkerJob::class);
    }

    public function test_first_campaign_dispatches_ensure_job(): void
    {
        $this->lifecycle->onCampaignStarted($this->campaign(42, 9001));

        Queue::assertPushed(EnsureOciCampaignWorkerJob::class);
        $this->assertSame([
            '9001' => true,
        ], $this->lifecycle->activeCampaignIds());
    }

    public function test_last_campaign_finished_dispatches_teardown_job(): void
    {
        $campaign = $this->campaign(7, 700);

        $this->lifecycle->onCampaignStarted($campaign);
        Queue::fake();

        $this->lifecycle->onCampaignFinished($campaign);

        Queue::assertPushed(TeardownOciCampaignWorkerJob::class);
        $this->assertSame([], $this->lifecycle->activeCampaignIds());
    }

    public function test_finish_on_oci_worker_hops_to_release_job(): void
    {
        config(['oci-workers.enabled' => false]); // simulates ephemeral CI env

        $campaign = $this->campaign(7, 700);
        $this->lifecycle->storeActiveCampaignIds(['700' => true]);

        $this->lifecycle->onCampaignFinished($campaign);

        Queue::assertPushed(ReleaseOciCampaignWorkerRefJob::class, function (ReleaseOciCampaignWorkerRefJob $job): bool {
            return $job->campaignId === 7
                && $job->workerRef === 700
                && $job->queue === 'provisioning';
        });
        Queue::assertNotPushed(TeardownOciCampaignWorkerJob::class);
        // Redis not cleared yet — hop job will do it on main app.
        $this->assertSame(['700' => true], $this->lifecycle->activeCampaignIds());
    }

    public function test_teardown_skipped_while_other_campaigns_active(): void
    {
        $a = $this->campaign(1, 101);
        $b = $this->campaign(2, 102);

        $this->lifecycle->onCampaignStarted($a);
        $this->lifecycle->onCampaignStarted($b);
        Queue::fake();

        $this->lifecycle->onCampaignFinished($a);

        Queue::assertNotPushed(TeardownOciCampaignWorkerJob::class);
        $this->assertSame([
            '102' => true,
        ], $this->lifecycle->activeCampaignIds());
    }

    public function test_same_local_campaign_id_different_worker_refs_do_not_collide(): void
    {
        $a = $this->campaign(5, 501);
        $b = $this->campaign(5, 502);

        $this->lifecycle->onCampaignStarted($a);
        $this->lifecycle->onCampaignStarted($b);
        Queue::fake();

        $this->lifecycle->onCampaignFinished($a);

        Queue::assertNotPushed(TeardownOciCampaignWorkerJob::class);
        $this->assertSame([
            '502' => true,
        ], $this->lifecycle->activeCampaignIds());
    }

    public function test_ensure_worker_creates_and_stores_ocid(): void
    {
        $this->lifecycle->storeActiveCampaignIds([
            '99' => true,
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
        $this->lifecycle->onCampaignStarted($this->campaign(55, 5500, 25000));

        $this->assertSame([
            '5500' => 25000,
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
        $this->lifecycle->storeActiveCampaignIds(['1' => true]);
        $this->lifecycle->storeCampaignLoad(['1' => 100]);
        $this->lifecycle->storeInstanceOcid('ocid1.containerinstance.oc1.test.force');
        $this->lifecycle->storeInstanceStartedAt(now()->subHour()->toIso8601String());

        $result = $this->lifecycle->forceDestroy(app(OciContainerInstanceClient::class));

        $this->assertContains('ocid1.containerinstance.oc1.test.force', $result['deleted']);
        $this->assertNull($this->lifecycle->instanceOcid());
        $this->assertSame([], $this->lifecycle->activeCampaignIds());
        $this->assertIsArray($result['session']);
        $this->assertGreaterThanOrEqual(3500, $result['session']['active_seconds']);
    }

    public function test_release_clears_legacy_aliases(): void
    {
        $this->lifecycle->storeActiveCampaignIds([
            '12' => true,
            '_:12' => true,
            'acme:12' => true,
            '900' => true,
            '999' => true,
        ]);

        Queue::fake();
        $this->lifecycle->releaseCampaignRef('acme', 12, 900);

        $this->assertSame([
            '999' => true,
        ], $this->lifecycle->activeCampaignIds());
        Queue::assertNotPushed(TeardownOciCampaignWorkerJob::class);
    }

    public function test_prune_removes_worker_ref_when_campaign_not_sending(): void
    {
        tenancy()->central(function (): void {
            \Illuminate\Support\Facades\DB::table('campaign_worker_refs')->insert([
                'id' => 5001,
                'tenant_id' => 'gone-tenant',
                'tenant_campaign_id' => 99,
                'campaign_uuid' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->lifecycle->storeActiveCampaignIds([
            '5001' => true,
            '9001' => true, // no central row — kept
        ]);
        $this->lifecycle->storeInstanceOcid('ocid1.containerinstance.oc1.test.prune');

        Queue::fake();
        $removed = $this->lifecycle->pruneStaleActiveCampaignRefs();

        $this->assertSame(1, $removed);
        $this->assertSame(['9001' => true], $this->lifecycle->activeCampaignIds());
        Queue::assertNotPushed(TeardownOciCampaignWorkerJob::class);
    }

    public function test_prune_schedules_teardown_when_last_stale_ref_removed(): void
    {
        tenancy()->central(function (): void {
            \Illuminate\Support\Facades\DB::table('campaign_worker_refs')->insert([
                'id' => 5002,
                'tenant_id' => 'missing-tenant',
                'tenant_campaign_id' => 7,
                'campaign_uuid' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->lifecycle->storeActiveCampaignIds(['5002' => true]);
        $this->lifecycle->storeInstanceOcid('ocid1.containerinstance.oc1.test.last');

        Queue::fake();
        $removed = $this->lifecycle->pruneStaleActiveCampaignRefs();

        $this->assertSame(1, $removed);
        $this->assertSame([], $this->lifecycle->activeCampaignIds());
        Queue::assertPushed(TeardownOciCampaignWorkerJob::class);
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
