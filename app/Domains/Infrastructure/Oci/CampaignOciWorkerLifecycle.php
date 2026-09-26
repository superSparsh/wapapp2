<?php

declare(strict_types=1);

namespace App\Domains\Infrastructure\Oci;

use App\Domains\Infrastructure\Oci\Contracts\OciContainerInstanceClient;
use App\Domains\Infrastructure\Oci\Jobs\EnsureOciCampaignWorkerJob;
use App\Domains\Infrastructure\Oci\Jobs\TeardownOciCampaignWorkerJob;
use App\Models\Campaign;
use App\Support\OciWorkload;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Refcounted shared campaign Container Instance lifecycle.
 *
 * First sending campaign → ensure CI exists.
 * Last completed/cancelled campaign → destroy CI after grace (if queue drained).
 *
 * State is stored on the Redis connection directly (not Cache facade) so tenant
 * cache tags cannot hide keys from queue workers running without tenancy.
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
            Log::info('OCI ephemeral: onCampaignStarted skipped — feature disabled');

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
            Log::info('OCI ephemeral: ensure skipped — feature disabled');

            return;
        }

        $this->withLock(function () use ($client): void {
            $active = $this->activeCampaignIds();
            if ($active === []) {
                Log::info('OCI ephemeral: ensure skipped — no active campaigns in redis state');

                return;
            }

            $existing = $this->instanceOcid();
            if ($existing !== null) {
                Log::info('OCI ephemeral: ensure skipped — worker already provisioned', [
                    'ocid' => $existing,
                ]);

                return;
            }

            if (! $client->isConfigured()) {
                Log::warning('OCI ephemeral: cannot provision — client not configured.');

                return;
            }

            $prefix = (string) config('oci-workers.ephemeral.display_name_prefix', 'wapapp-campaign-worker');
            $displayName = $prefix.'-'.now()->format('Ymd-His');
            $environment = $this->workerEnvironment();

            Log::info('OCI ephemeral: provisioning campaign worker', [
                'display_name' => $displayName,
                'active' => array_keys($active),
                'driver' => (string) config('oci-workers.ephemeral.driver', 'log'),
            ]);

            $created = $client->createCampaignWorker($displayName, $environment);
            $this->storeInstanceOcid($created['ocid']);

            Log::info('OCI ephemeral: stored campaign worker ocid', [
                'ocid' => $created['ocid'],
            ]);
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

            $ocid = $this->instanceOcid();
            if ($ocid === null) {
                Log::info('OCI ephemeral: teardown skipped — no instance OCID in redis state');

                return;
            }

            $client->delete($ocid);
            $this->forgetInstanceOcid();
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
    public function activeCampaignIds(): array
    {
        $raw = $this->redis()->get($this->redisKey(self::CACHE_ACTIVE_CAMPAIGNS));
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $id) {
            $out[(int) $id] = true;
        }

        return $out;
    }

    /**
     * @param  array<int, true>  $active
     */
    public function storeActiveCampaignIds(array $active): void
    {
        $this->redis()->set(
            $this->redisKey(self::CACHE_ACTIVE_CAMPAIGNS),
            json_encode(array_map('intval', array_keys($active))),
        );
    }

    public function instanceOcid(): ?string
    {
        $value = $this->redis()->get($this->redisKey(self::CACHE_INSTANCE_OCID));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function storeInstanceOcid(string $ocid): void
    {
        $this->redis()->set($this->redisKey(self::CACHE_INSTANCE_OCID), $ocid);
    }

    public function forgetInstanceOcid(): void
    {
        $this->redis()->del($this->redisKey(self::CACHE_INSTANCE_OCID));
    }

    private function withLock(callable $callback): void
    {
        $lockKey = $this->redisKey(self::CACHE_LOCK);
        $token = bin2hex(random_bytes(8));
        $deadline = microtime(true) + 20;

        while (microtime(true) < $deadline) {
            $acquired = (bool) $this->redis()->set($lockKey, $token, 'EX', 30, 'NX');
            if ($acquired) {
                try {
                    $callback();
                } finally {
                    if ($this->redis()->get($lockKey) === $token) {
                        $this->redis()->del($lockKey);
                    }
                }

                return;
            }

            usleep(100_000);
        }

        throw new \RuntimeException('OCI ephemeral: could not acquire campaign worker lock.');
    }

    private function redisKey(string $name): string
    {
        return $name;
    }

    private function redis(): \Illuminate\Redis\Connections\Connection
    {
        $connection = (string) config('cache.stores.redis.connection', 'cache');

        try {
            return Redis::connection($connection);
        } catch (\Throwable) {
            return Redis::connection();
        }
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
