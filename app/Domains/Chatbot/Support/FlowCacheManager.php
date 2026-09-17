<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Support;

use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Illuminate\Filesystem\Filesystem;

/**
 * Manages cached node-maps for chatbot flows.
 *
 * Uses a dedicated FileStore instance (bypassing the Cache facade) so the
 * tenancy CacheTenancyBootstrapper never wraps calls with cache-tags,
 * which the file/database drivers do not support.
 *
 * Cache keys are versioned with the flow's updated_at + exported_data hash so
 * a failed/missed bust can never serve stale triggers after Save Flow.
 */
class FlowCacheManager
{
    private ?Repository $store = null;

    private ?string $storePath = null;

    /**
     * Cache a flow's normalized node map for fast repeated access.
     *
     * @param  array<string, array<string, mixed>>  $nodeMap
     */
    public function putNodeMap(int $flowId, array $nodeMap, string $version = ''): void
    {
        try {
            $this->store()->put(
                $this->nodeMapKey($flowId, $version),
                $nodeMap,
                (int) config('chatbot.flow_cache_ttl_seconds', 7200),
            );
        } catch (\Throwable) {
            // Silently skip caching on failure – the normalizer will re-build.
        }
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    public function getNodeMap(int $flowId, string $version = ''): ?array
    {
        try {
            $cached = $this->store()->get($this->nodeMapKey($flowId, $version));

            return is_array($cached) ? $cached : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function forgetNodeMap(int $flowId, string $version = ''): void
    {
        try {
            $store = $this->store();
            // Drop versioned key and legacy unversioned key from older builds.
            $store->forget($this->nodeMapKey($flowId, $version));
            $store->forget($this->legacyNodeMapKey($flowId));
        } catch (\Throwable) {
            // Nothing to clear – that's fine.
        }
    }

    /**
     * Build a cache version that changes whenever flow content changes.
     *
     * @param  array<string, mixed>|null  $exportedData
     */
    public function versionFor(?\DateTimeInterface $updatedAt, ?array $exportedData): string
    {
        $timestamp = $updatedAt?->getTimestamp() ?? 0;
        $fingerprint = substr(hash('sha256', json_encode($exportedData ?? []) ?: ''), 0, 16);

        return "{$timestamp}:{$fingerprint}";
    }

    private function nodeMapKey(int $flowId, string $version): string
    {
        if ($version === '') {
            return $this->legacyNodeMapKey($flowId);
        }

        return "chatbot:flow:{$flowId}:nodes:{$version}";
    }

    private function legacyNodeMapKey(int $flowId): string
    {
        return "chatbot:flow:{$flowId}:nodes";
    }

    /**
     * Build a standalone file-cache repository that bypasses the CacheManager.
     *
     * The tenancy CacheTenancyBootstrapper intercepts all Cache::store() calls
     * and wraps them with cache-tags.  The file/database drivers do not support
     * tagging, so those calls throw "This cache store does not support tagging."
     *
     * By constructing a FileStore directly we avoid the tag wrapping entirely.
     * Tenant isolation is maintained because storage_path() is overridden by
     * the FilesystemTenancyBootstrapper to include the tenant ID.
     *
     * Re-create the store when storage_path() changes so a singleton manager
     * never keeps writing/reading another tenant's (or central) cache root.
     */
    private function store(): Repository
    {
        $path = storage_path('framework/cache/chatbot');

        if ($this->store === null || $this->storePath !== $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0755, true);
            }

            $this->storePath = $path;
            $this->store = new Repository(
                new FileStore(new Filesystem(), $path),
            );
        }

        return $this->store;
    }
}
