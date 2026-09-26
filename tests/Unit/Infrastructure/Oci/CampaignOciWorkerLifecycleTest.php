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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignOciWorkerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();

        config([
            'oci-workers.enabled' => true,
            'oci-workers.ephemeral.enabled' => true,
            'oci-workers.ephemeral.driver' => 'log',
            'oci-workers.ephemeral.grace_seconds' => 60,
            'oci-workers.ephemeral.provisioning_queue' => 'provisioning',
        ]);

        $this->app->bind(OciContainerInstanceClient::class, LogOciContainerInstanceClient::class);
    }

    public function test_disabled_when_flag_off(): void
    {
        config(['oci-workers.ephemeral.enabled' => false]);

        $lifecycle = app(CampaignOciWorkerLifecycle::class);
        $campaign = new Campaign(['id' => 1]);

        $lifecycle->onCampaignStarted($campaign);

        Queue::assertNotPushed(EnsureOciCampaignWorkerJob::class);
    }

    public function test_first_campaign_dispatches_ensure_job(): void
    {
        $lifecycle = app(CampaignOciWorkerLifecycle::class);
        $campaign = new Campaign;
        $campaign->id = 42;

        $lifecycle->onCampaignStarted($campaign);

        Queue::assertPushed(EnsureOciCampaignWorkerJob::class);
        $this->assertSame([42], Cache::get(CampaignOciWorkerLifecycle::CACHE_ACTIVE_CAMPAIGNS));
    }

    public function test_last_campaign_finished_dispatches_teardown_job(): void
    {
        $lifecycle = app(CampaignOciWorkerLifecycle::class);
        $campaign = new Campaign;
        $campaign->id = 7;

        $lifecycle->onCampaignStarted($campaign);
        Queue::fake(); // reset after ensure

        $lifecycle->onCampaignFinished($campaign);

        Queue::assertPushed(TeardownOciCampaignWorkerJob::class);
        $this->assertSame([], Cache::get(CampaignOciWorkerLifecycle::CACHE_ACTIVE_CAMPAIGNS));
    }

    public function test_teardown_skipped_while_other_campaigns_active(): void
    {
        $lifecycle = app(CampaignOciWorkerLifecycle::class);

        $a = new Campaign;
        $a->id = 1;
        $b = new Campaign;
        $b->id = 2;

        $lifecycle->onCampaignStarted($a);
        $lifecycle->onCampaignStarted($b);
        Queue::fake();

        $lifecycle->onCampaignFinished($a);

        Queue::assertNotPushed(TeardownOciCampaignWorkerJob::class);
        $this->assertSame([2], Cache::get(CampaignOciWorkerLifecycle::CACHE_ACTIVE_CAMPAIGNS));
    }

    public function test_ensure_worker_creates_and_stores_ocid(): void
    {
        Cache::forever(CampaignOciWorkerLifecycle::CACHE_ACTIVE_CAMPAIGNS, [99]);

        $lifecycle = app(CampaignOciWorkerLifecycle::class);
        $client = app(OciContainerInstanceClient::class);

        $lifecycle->ensureWorker($client);

        $ocid = Cache::get(CampaignOciWorkerLifecycle::CACHE_INSTANCE_OCID);
        $this->assertIsString($ocid);
        $this->assertStringStartsWith('ocid1.containerinstance.', $ocid);
    }

    public function test_teardown_worker_deletes_when_idle(): void
    {
        Cache::forever(CampaignOciWorkerLifecycle::CACHE_ACTIVE_CAMPAIGNS, []);
        Cache::forever(CampaignOciWorkerLifecycle::CACHE_INSTANCE_OCID, 'ocid1.containerinstance.oc1.test.deadbeef');

        $lifecycle = app(CampaignOciWorkerLifecycle::class);
        $client = app(OciContainerInstanceClient::class);

        $lifecycle->teardownWorker($client);

        $this->assertNull(Cache::get(CampaignOciWorkerLifecycle::CACHE_INSTANCE_OCID));
    }
}
