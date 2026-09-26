<?php

declare(strict_types=1);

namespace App\Domains\Infrastructure\Oci;

use App\Domains\Infrastructure\Oci\Contracts\OciContainerInstanceClient;
use App\Domains\Infrastructure\Oci\Jobs\EnsureOciCampaignWorkerJob;
use App\Domains\Infrastructure\Oci\Jobs\TeardownOciCampaignWorkerJob;
use App\Models\Campaign;
use App\Support\OciWorkload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Refcounted shared campaign Container Instance lifecycle.
 *
 * First sending campaign → ensure CI exists.
 * Last completed/cancelled campaign → destroy CI after grace (if queue drained).
 */
final class CampaignOciWorkerLifecycle
{
    public const CACHE_INSTANCE_OCID = 'oci.campaign_worker.instance_ocid';

    public const CACHE_ACTIVE_CAMPAIGNS = 'oci.campaign_worker.active_campaign_ids';

    public const CACHE_LOCK = 'oci.campaign_worker.lock';

    public function enabled(): bool
    {
        return OciWorkload::enabled()
            && (bool) config('oci-workers.ephemeral.enabled', false);
    }

    public function onCampaignStarted(Campaign $campaign): void
    {
        if (! $this->enabled()) {
            return;
        }

        $campaignId = (int) $campaign->id;

        $this->withLock(function () use ($campaignId): void {
            $active = $this->activeCampaignIds();
            $active[$campaignId] = true;
            $this->storeActiveCampaignIds($active);
        });

        EnsureOciCampaignWorkerJob::dispatch()
            ->onQueue((string) config('oci-workers.ephemeral.provisioning_queue', 'provisioning'));
    }

    public function onCampaignFinished(Campaign $campaign): void
    {
        if (! $this->enabled()) {
            return;
        }

        $campaignId = (int) $campaign->id;
        $shouldTeardown = false;

        $this->withLock(function () use ($campaignId, &$shouldTeardown): void {
            $active = $this->activeCampaignIds();
            unset($active[$campaignId]);
            $this->storeActiveCampaignIds($active);
            $shouldTeardown = $active === [];
        });

        if (! $shouldTeardown) {
            return;
        }

        $grace = max(0, (int) config('oci-workers.ephemeral.grace_seconds', 120));

        TeardownOciCampaignWorkerJob::dispatch()
            ->delay(now()->addSeconds($grace))
            ->onQueue((string) config('oci-workers.ephemeral.provisioning_queue', 'provisioning'));
    }

    public function ensureWorker(OciContainerInstanceClient $client): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->withLock(function () use ($client): void {
            $active = $this->activeCampaignIds();
            if ($active === []) {
                return;
            }

            $existing = Cache::get(self::CACHE_INSTANCE_OCID);
            if (is_string($existing) && $existing !== '') {
                return;
            }

            if (! $client->isConfigured()) {
                Log::warning('OCI ephemeral: cannot provision — client not configured.');

                return;
            }

            $prefix = (string) config('oci-workers.ephemeral.display_name_prefix', 'wapapp-campaign-worker');
            $displayName = $prefix.'-'.now()->format('Ymd-His');
            $environment = $this->workerEnvironment();

            $created = $client->createCampaignWorker($displayName, $environment);
            Cache::forever(self::CACHE_INSTANCE_OCID, $created['ocid']);
        });
    }

    public function teardownWorker(OciContainerInstanceClient $client): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->withLock(function () use ($client): void {
            $active = $this->activeCampaignIds();
            if ($active !== []) {
                Log::info('OCI ephemeral: skip teardown — campaigns still active', [
                    'active' => array_keys($active),
                ]);

                return;
            }

            if ($this->campaignQueueDepth() > 0) {
                Log::info('OCI ephemeral: skip teardown — campaign queue not empty', [
                    'depth' => $this->campaignQueueDepth(),
                ]);

                TeardownOciCampaignWorkerJob::dispatch()
                    ->delay(now()->addSeconds(max(30, (int) config('oci-workers.ephemeral.grace_seconds', 120))))
                    ->onQueue((string) config('oci-workers.ephemeral.provisioning_queue', 'provisioning'));

                return;
            }

            $ocid = Cache::get(self::CACHE_INSTANCE_OCID);
            if (! is_string($ocid) || $ocid === '') {
                return;
            }

            $client->delete($ocid);
            Cache::forget(self::CACHE_INSTANCE_OCID);
        });
    }

    /**
     * @return array<string, string>
     */
    public function workerEnvironment(): array
    {
        $base = (array) config('oci-workers.ephemeral.container_environment', []);

        $fromApp = array_filter([
            'APP_ENV' => (string) config('app.env'),
            'APP_KEY' => (string) config('app.key'),
            'APP_URL' => (string) config('app.url'),
            'DB_CONNECTION' => (string) config('database.default'),
            'DB_HOST' => (string) config('database.connections.mysql.host', ''),
            'DB_PORT' => (string) config('database.connections.mysql.port', '3306'),
            'DB_DATABASE' => (string) config('database.connections.mysql.database', ''),
            'DB_USERNAME' => (string) config('database.connections.mysql.username', ''),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password', ''),
            'REDIS_CLIENT' => (string) config('database.redis.client', 'phpredis'),
            'REDIS_HOST' => (string) config('database.redis.default.host', '127.0.0.1'),
            'REDIS_PASSWORD' => (string) (config('database.redis.default.password') ?? ''),
            'REDIS_PORT' => (string) config('database.redis.default.port', 6379),
            'REDIS_DB' => (string) config('database.redis.default.database', 0),
            'QUEUE_CONNECTION' => 'redis',
            'CAMPAIGN_QUEUE' => OciWorkload::campaignQueue(),
            'HORIZON_ROLE' => 'oci-heavy',
            'OCI_WORKERS_ENABLED' => 'false',
        ], static fn ($v) => $v !== null && $v !== '');

        return array_merge($fromApp, $base);
    }

    /**
     * @return array<int, true>
     */
    private function activeCampaignIds(): array
    {
        $raw = Cache::get(self::CACHE_ACTIVE_CAMPAIGNS, []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $id) {
            $out[(int) $id] = true;
        }

        return $out;
    }

    /**
     * @param  array<int, true>  $active
     */
    private function storeActiveCampaignIds(array $active): void
    {
        Cache::forever(self::CACHE_ACTIVE_CAMPAIGNS, array_map('intval', array_keys($active)));
    }

    private function withLock(callable $callback): void
    {
        $lock = Cache::lock(self::CACHE_LOCK, 30);
        $lock->block(20, $callback);
    }

    private function campaignQueueDepth(): int
    {
        try {
            $queue = OciWorkload::campaignQueue();
            $connection = config('queue.connections.redis.connection', 'default');
            $prefix = (string) config('database.redis.options.prefix', '');
            $key = $prefix.'queues:'.$queue;

            return (int) Redis::connection($connection)->llen($key);
        } catch (\Throwable $e) {
            Log::warning('OCI ephemeral: unable to read campaign queue depth', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
