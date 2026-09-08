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
 */
class FlowCacheManager
{
    private ?Repository $store = null;

    /**
     * Cache a flow's normalized node map for fast repeated access.
     *
     * @param  array<string, array<string, mixed>>  $nodeMap
     */
    public function putNodeMap(int $flowId, array $nodeMap): void
    {
        try {
            $this->store()->put(
                $this->nodeMapKey($flowId),
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
    public function getNodeMap(int $flowId): ?array
    {
        try {
            $cached = $this->store()->get($this->nodeMapKey($flowId));

            return is_array($cached) ? $cached : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function forgetNodeMap(int $flowId): void
    {
        try {
            $this->store()->forget($this->nodeMapKey($flowId));
        } catch (\Throwable) {
            // Nothing to clear – that's fine.
        }
    }

    private function nodeMapKey(int $flowId): string
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
     */
    private function store(): Repository
    {
        if ($this->store === null) {
            $path = storage_path('framework/cache/chatbot');

            if (! is_dir($path)) {
                @mkdir($path, 0755, true);
            }

            $this->store = new Repository(
                new FileStore(new Filesystem(), $path),
            );
        }

        return $this->store;
    }
}
