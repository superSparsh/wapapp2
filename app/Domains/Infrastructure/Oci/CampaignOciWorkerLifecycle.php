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
 * First sending campaign → ensure CI exists (auto-sized by recipient demand).
 * Last completed/cancelled campaign → destroy CI after grace (if queue drained).
 *
 * State is stored on the Redis connection directly (not Cache facade) so tenant
 * cache tags cannot hide keys from queue workers running without tenancy.
 */
final class CampaignOciWorkerLifecycle
{
    public const CACHE_INSTANCE_OCID = 'oci.campaign_worker.instance_ocid';

    public const CACHE_ACTIVE_CAMPAIGNS = 'oci.campaign_worker.active_campaign_ids';

    public const CACHE_CAMPAIGN_LOAD = 'oci.campaign_worker.campaign_load';

    public const CACHE_INSTANCE_STARTED_AT = 'oci.campaign_worker.instance_started_at';

    public const CACHE_LAST_SESSION = 'oci.campaign_worker.last_session';

    public const CACHE_LOCK = 'oci.campaign_worker.lock';

    public function enabled(): bool
    {
        return OciWorkload::enabled()
            && (bool) config('oci-workers.ephemeral.enabled', false);
    }

    public function onCampaignStarted(Campaign $campaign, ?string $tenantId = null): void
    {
        if (! $this->enabled()) {
            Log::info('OCI ephemeral: onCampaignStarted skipped - feature disabled');

            return;
        }

        $ref = $this->campaignRefKeyOrNull($campaign, $tenantId);
        if ($ref === null) {
            Log::warning('OCI ephemeral: onCampaignStarted skipped - tenant context or campaign id missing', [
                'campaign_id' => $campaign->id ?? null,
                'tenancy_initialized' => function_exists('tenancy') && tenancy()->initialized,
            ]);

            return;
        }

        $recipients = max(0, (int) ($campaign->total_recipients ?? 0));

        $this->withLock(function () use ($ref, $recipients): void {
            $active = $this->activeCampaignIds();
            $active[$ref] = true;
            $this->storeActiveCampaignIds($active);

            $load = $this->campaignLoad();
            $load[$ref] = $recipients;
            $this->storeCampaignLoad($load);
        });

        EnsureOciCampaignWorkerJob::dispatch()
            ->onQueue((string) config('oci-workers.ephemeral.provisioning_queue', 'provisioning'));
    }

    public function onCampaignFinished(Campaign $campaign, ?string $tenantId = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $shouldTeardown = false;

        $this->withLock(function () use ($campaign, $tenantId, &$shouldTeardown): void {
            $this->forgetCampaignRefs($campaign, $tenantId);
            $shouldTeardown = $this->activeCampaignIds() === [];
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
            Log::info('OCI ephemeral: ensure skipped - feature disabled');

            return;
        }

        $this->withLock(function () use ($client): void {
            $active = $this->activeCampaignIds();
            if ($active === []) {
                Log::info('OCI ephemeral: ensure skipped - no active campaigns in redis state');

                return;
            }

            $existing = $this->instanceOcid();
            if ($existing !== null) {
                Log::info('OCI ephemeral: ensure skipped - worker already provisioned', [
                    'ocid' => $existing,
                ]);

                return;
            }

            if (! $client->isConfigured()) {
                Log::warning('OCI ephemeral: cannot provision - client not configured.');

                return;
            }

            $shape = $this->resolveShape();
            $prefix = (string) config('oci-workers.ephemeral.display_name_prefix', 'wapapp-campaign-worker');
            $displayName = $prefix.'-'.now()->format('Ymd-His');
            $environment = $this->workerEnvironment($shape);

            Log::info('OCI ephemeral: provisioning campaign worker', [
                'display_name' => $displayName,
                'active' => array_keys($active),
                'max_recipients' => $this->maxRecipientDemand(),
                'ocpus' => $shape['ocpus'],
                'memory_in_gbs' => $shape['memory_in_gbs'],
                'campaign_max_processes' => $shape['campaign_max_processes'],
                'driver' => (string) config('oci-workers.ephemeral.driver', 'log'),
            ]);

            try {
                $created = $client->createCampaignWorker($displayName, $environment, [
                    'ocpus' => $shape['ocpus'],
                    'memory_in_gbs' => $shape['memory_in_gbs'],
                ]);
            } catch (\Throwable $e) {
                Log::error('OCI ephemeral: create threw', [
                    'error' => $e->getMessage(),
                    'display_name' => $displayName,
                ]);

                throw $e;
            }

            $this->storeInstanceOcid($created['ocid']);
            $this->storeInstanceStartedAt(now()->toIso8601String());

            Log::info('OCI ephemeral: stored campaign worker ocid', [
                'ocid' => $created['ocid'],
                'started_at' => $this->instanceStartedAt(),
            ]);
        });
    }

    /**
     * Force-destroy the tracked campaign worker (and optional OCI orphans), clear Redis state,
     * and record how long the container was active.
     *
     * @return array{deleted: list<string>, session: array<string, mixed>|null}
     */
    public function forceDestroy(OciContainerInstanceClient $client, bool $includeOrphans = true): array
    {
        $deleted = [];

        $this->withLock(function () use ($client, $includeOrphans, &$deleted): void {
            $candidates = [];
            $tracked = $this->instanceOcid();
            if ($tracked !== null && $tracked !== '') {
                $candidates[] = $tracked;
            }

            if ($includeOrphans) {
                try {
                    foreach ($client->listCampaignWorkerOcids() as $ocid) {
                        if ($ocid !== '' && ! in_array($ocid, $candidates, true)) {
                            $candidates[] = $ocid;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('OCI ephemeral: list orphans during forceDestroy failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            foreach ($candidates as $ocid) {
                try {
                    $client->delete($ocid);
                    $deleted[] = $ocid;
                } catch (\Throwable $e) {
                    Log::error('OCI ephemeral: forceDestroy delete failed', [
                        'ocid' => $ocid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $session = $this->closeSession();
            $this->forgetInstanceOcid();
            $this->forgetInstanceStartedAt();
            $this->forgetCampaignLoad();
            $this->storeActiveCampaignIds([]);

            if ($session !== null) {
                $this->storeLastSession($session);
                Log::info('OCI ephemeral: force-destroyed campaign worker(s)', [
                    'deleted' => $deleted,
                    'active_seconds' => $session['active_seconds'] ?? null,
                    'started_at' => $session['started_at'] ?? null,
                    'ended_at' => $session['ended_at'] ?? null,
                ]);
            }
        });

        return [
            'deleted' => $deleted,
            'session' => $this->lastSession(),
        ];
    }

    public function teardownWorker(OciContainerInstanceClient $client): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->withLock(function () use ($client): void {
            $active = $this->activeCampaignIds();
            if ($active !== []) {
                Log::info('OCI ephemeral: skip teardown - campaigns still active', [
                    'active' => array_keys($active),
                ]);

                return;
            }

            // No active campaigns left. Do not block forever on leftover queue jobs.
            $depth = $this->campaignQueueDepth();
            if ($depth > 0) {
                Log::info('OCI ephemeral: destroying despite leftover campaign queue jobs', [
                    'depth' => $depth,
                ]);
            }

            $toDelete = [];
            $tracked = $this->instanceOcid();
            if ($tracked !== null && $tracked !== '') {
                $toDelete[] = $tracked;
            }

            try {
                foreach ($client->listCampaignWorkerOcids() as $ocid) {
                    if ($ocid !== '' && ! in_array($ocid, $toDelete, true)) {
                        $toDelete[] = $ocid;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('OCI ephemeral: list orphans during teardown failed', [
                    'error' => $e->getMessage(),
                ]);
            }

            if ($toDelete === []) {
                $this->forgetInstanceOcid();
                $this->forgetInstanceStartedAt();
                $this->forgetCampaignLoad();
                $this->storeActiveCampaignIds([]);
                Log::info('OCI ephemeral: teardown skipped - no tracked OCID and no listed instances');

                return;
            }

            foreach ($toDelete as $ocid) {
                try {
                    $client->delete($ocid);
                } catch (\Throwable $e) {
                    Log::error('OCI ephemeral: delete failed during teardown', [
                        'ocid' => $ocid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $session = $this->closeSession();
            $this->forgetInstanceOcid();
            $this->forgetInstanceStartedAt();
            $this->forgetCampaignLoad();
            $this->storeActiveCampaignIds([]);
            if ($session !== null) {
                $this->storeLastSession($session);
                Log::info('OCI ephemeral: worker session closed', [
                    'deleted' => $toDelete,
                    'active_seconds' => $session['active_seconds'],
                    'started_at' => $session['started_at'],
                    'ended_at' => $session['ended_at'],
                ]);
            }

            Log::info('OCI ephemeral: destroyed campaign worker(s)', ['deleted' => $toDelete]);
        });
    }

    /**
     * Pick OCPU / RAM / Horizon campaign processes from recipient demand.
     *
     * @return array{ocpus: float, memory_in_gbs: float, campaign_max_processes: int}
     */
    public function resolveShape(?int $maxRecipients = null): array
    {
        $fallback = [
            'ocpus' => (float) config('oci-workers.ephemeral.ocpus', 1),
            'memory_in_gbs' => (float) config('oci-workers.ephemeral.memory_in_gbs', 4),
            'campaign_max_processes' => 2,
        ];

        if (! (bool) config('oci-workers.ephemeral.auto_size.enabled', true)) {
            return $fallback;
        }

        $demand = $maxRecipients ?? $this->maxRecipientDemand();
        /** @var list<array{max_recipients?: int|null, ocpus?: float|int, memory_in_gbs?: float|int, campaign_max_processes?: int}> $tiers */
        $tiers = (array) config('oci-workers.ephemeral.auto_size.tiers', []);

        foreach ($tiers as $tier) {
            $cap = $tier['max_recipients'] ?? null;
            if ($cap !== null && $demand > (int) $cap) {
                continue;
            }

            return [
                'ocpus' => (float) ($tier['ocpus'] ?? $fallback['ocpus']),
                'memory_in_gbs' => (float) ($tier['memory_in_gbs'] ?? $fallback['memory_in_gbs']),
                'campaign_max_processes' => max(1, (int) ($tier['campaign_max_processes'] ?? $fallback['campaign_max_processes'])),
            ];
        }

        return $fallback;
    }

    public function maxRecipientDemand(): int
    {
        $load = $this->campaignLoad();
        if ($load === []) {
            return 0;
        }

        return (int) max($load);
    }

    /**
     * Stable cross-tenant Redis key: "{tenant_id}:{campaign_id}".
     * Never falls back to "_" — missing tenant must not invent a colliding key.
     */
    public function campaignRefKey(Campaign $campaign, ?string $tenantId = null): string
    {
        $ref = $this->campaignRefKeyOrNull($campaign, $tenantId);
        if ($ref === null) {
            throw new \RuntimeException('OCI ephemeral: tenant context and campaign id are required for campaign ref.');
        }

        return $ref;
    }

    public function campaignRefKeyOrNull(Campaign $campaign, ?string $tenantId = null): ?string
    {
        $resolvedTenantId = $this->resolveTenantId($tenantId);
        $campaignId = (int) ($campaign->id ?? 0);

        if ($resolvedTenantId === null || $campaignId < 1) {
            return null;
        }

        return $resolvedTenantId.':'.$campaignId;
    }

    /**
     * Clear this tenant's ref + legacy bare numeric id only.
     * Does not touch other tenants' "{other}:{same_id}" keys.
     */
    public function forgetCampaignRefs(Campaign $campaign, ?string $tenantId = null): void
    {
        $campaignId = (int) ($campaign->id ?? 0);
        $aliases = array_values(array_unique(array_filter([
            $this->campaignRefKeyOrNull($campaign, $tenantId),
            $campaignId > 0 ? (string) $campaignId : null,
        ])));

        if ($aliases === []) {
            Log::warning('OCI ephemeral: forgetCampaignRefs skipped - no resolvable refs', [
                'campaign_id' => $campaign->id ?? null,
                'tenancy_initialized' => function_exists('tenancy') && tenancy()->initialized,
            ]);

            return;
        }

        $active = $this->activeCampaignIds();
        $load = $this->campaignLoad();

        foreach ($aliases as $ref) {
            unset($active[$ref], $load[$ref]);
        }

        $this->storeActiveCampaignIds($active);
        $this->storeCampaignLoad($load);
    }

    /**
     * @return array<string, int>
     */
    public function campaignLoad(): array
    {
        $raw = $this->redis()->get($this->redisKey(self::CACHE_CAMPAIGN_LOAD));
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $key => $recipients) {
            $ref = $this->parseCampaignRef($key);
            if ($ref === null) {
                continue;
            }
            $out[$ref] = max($out[$ref] ?? 0, max(0, (int) $recipients));
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $load
     */
    public function storeCampaignLoad(array $load): void
    {
        $normalized = [];
        foreach ($load as $key => $recipients) {
            $ref = $this->parseCampaignRef($key);
            if ($ref === null) {
                continue;
            }
            $normalized[$ref] = max(0, (int) $recipients);
        }

        $this->redis()->set(
            $this->redisKey(self::CACHE_CAMPAIGN_LOAD),
            json_encode($normalized),
        );
    }

    public function forgetCampaignLoad(): void
    {
        $this->redis()->del($this->redisKey(self::CACHE_CAMPAIGN_LOAD));
    }

    /**
     * @param  array{ocpus?: float, memory_in_gbs?: float, campaign_max_processes?: int}|null  $shape
     * @return array<string, string>
     */
    public function workerEnvironment(?array $shape = null): array
    {
        $shape ??= $this->resolveShape();
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
            'HORIZON_CAMPAIGN_MAX_PROCESSES' => (string) ($shape['campaign_max_processes'] ?? 2),
            'OCI_WORKERS_ENABLED' => 'false',
        ], static fn ($v) => $v !== null && $v !== '');

        return array_merge($fromApp, $base);
    }

    /**
     * @return array<string, true>
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
            $ref = $this->parseCampaignRef($id);
            if ($ref === null) {
                continue;
            }
            $out[$ref] = true;
        }

        return $out;
    }

    /**
     * @param  array<string, true>  $active
     */
    public function storeActiveCampaignIds(array $active): void
    {
        $refs = [];
        foreach (array_keys($active) as $key) {
            $ref = $this->parseCampaignRef($key);
            if ($ref !== null) {
                $refs[] = $ref;
            }
        }

        $this->redis()->set(
            $this->redisKey(self::CACHE_ACTIVE_CAMPAIGNS),
            json_encode(array_values(array_unique($refs))),
        );
    }

    /**
     * Keep "{tenant}:{campaign_id}" intact. Accept legacy bare numeric ids.
     * Drop "_" fallback keys and UUID leftovers (do not collapse tenants together).
     */
    private function parseCampaignRef(mixed $value): ?string
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $id = (int) $value;

            return $id > 0 ? (string) $id : null;
        }

        if (! is_string($value) || $value === '' || ! str_contains($value, ':')) {
            return null;
        }

        $pos = strrpos($value, ':');
        $tenantId = substr($value, 0, $pos);
        $idPart = substr($value, $pos + 1);

        if ($tenantId === '' || $tenantId === '_' || ! ctype_digit($idPart)) {
            return null;
        }

        $id = (int) $idPart;

        return $id > 0 ? $tenantId.':'.$id : null;
    }

    private function resolveTenantId(?string $explicit = null): ?string
    {
        if (is_string($explicit) && $explicit !== '' && $explicit !== '_') {
            return $explicit;
        }

        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        $id = tenant('id');
        if (! is_string($id) || $id === '' || $id === '_') {
            return null;
        }

        return $id;
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

    public function instanceStartedAt(): ?string
    {
        $value = $this->redis()->get($this->redisKey(self::CACHE_INSTANCE_STARTED_AT));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function storeInstanceStartedAt(string $iso8601): void
    {
        $this->redis()->set($this->redisKey(self::CACHE_INSTANCE_STARTED_AT), $iso8601);
    }

    public function forgetInstanceStartedAt(): void
    {
        $this->redis()->del($this->redisKey(self::CACHE_INSTANCE_STARTED_AT));
    }

    /**
     * @return array{started_at: string, ended_at: string, active_seconds: int}|null
     */
    public function lastSession(): ?array
    {
        $raw = $this->redis()->get($this->redisKey(self::CACHE_LAST_SESSION));
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array{started_at: string, ended_at: string, active_seconds: int}  $session
     */
    public function storeLastSession(array $session): void
    {
        $this->redis()->set($this->redisKey(self::CACHE_LAST_SESSION), json_encode($session));
    }

    /**
     * @return array{started_at: string, ended_at: string, active_seconds: int}|null
     */
    private function closeSession(): ?array
    {
        $startedAt = $this->instanceStartedAt();
        if ($startedAt === null) {
            return null;
        }

        try {
            $start = \Illuminate\Support\Carbon::parse($startedAt);
        } catch (\Throwable) {
            return null;
        }

        $end = now();

        return [
            'started_at' => $start->toIso8601String(),
            'ended_at' => $end->toIso8601String(),
            'active_seconds' => max(0, (int) $start->diffInSeconds($end)),
        ];
    }

    /**
     * Admin-facing snapshot of the shared campaign worker (current + last session).
     *
     * @return array{
     *   enabled: bool,
     *   ocid: string|null,
     *   started_at: string|null,
     *   active_seconds: int|null,
     *   active_for_humans: string|null,
     *   active_campaign_ids: list<string>,
     *   max_recipients: int,
     *   last_session: array{started_at: string, ended_at: string, active_seconds: int, active_for_humans: string}|null
     * }
     */
    public function statusSnapshot(): array
    {
        $startedAt = $this->instanceStartedAt();
        $activeSeconds = null;
        if ($startedAt !== null) {
            try {
                $activeSeconds = max(0, (int) \Illuminate\Support\Carbon::parse($startedAt)->diffInSeconds(now()));
            } catch (\Throwable) {
                $activeSeconds = null;
            }
        }

        $last = $this->lastSession();
        $lastFormatted = null;
        if (is_array($last)) {
            $secs = (int) ($last['active_seconds'] ?? 0);
            $lastFormatted = [
                'started_at' => (string) ($last['started_at'] ?? ''),
                'ended_at' => (string) ($last['ended_at'] ?? ''),
                'active_seconds' => $secs,
                'active_for_humans' => $this->formatDurationSeconds($secs),
            ];
        }

        return [
            'enabled' => $this->enabled(),
            'ocid' => $this->instanceOcid(),
            'started_at' => $startedAt,
            'active_seconds' => $activeSeconds,
            'active_for_humans' => $activeSeconds !== null ? $this->formatDurationSeconds($activeSeconds) : null,
            'active_campaign_ids' => array_keys($this->activeCampaignIds()),
            'max_recipients' => $this->maxRecipientDemand(),
            'last_session' => $lastFormatted,
        ];
    }

    public function formatDurationSeconds(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        if ($h > 0) {
            return sprintf('%dh %dm %ds', $h, $m, $s);
        }
        if ($m > 0) {
            return sprintf('%dm %ds', $m, $s);
        }

        return sprintf('%ds', $s);
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
