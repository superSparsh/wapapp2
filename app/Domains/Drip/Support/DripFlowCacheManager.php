<?php

declare(strict_types=1);

namespace App\Domains\Drip\Support;

use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Illuminate\Filesystem\Filesystem;

class DripFlowCacheManager
{
    private ?Repository $store = null;

    /**
     * @param  array<string, array<string, mixed>>  $nodeMap
     */
    public function putNodeMap(int $campaignId, array $nodeMap): void
    {
        try {
            $this->store()->put(
                $this->nodeMapKey($campaignId),
                $nodeMap,
                (int) config('chatbot.flow_cache_ttl_seconds', 7200),
            );
        } catch (\Throwable) {
        }
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    public function getNodeMap(int $campaignId): ?array
    {
        try {
            $cached = $this->store()->get($this->nodeMapKey($campaignId));

            return is_array($cached) ? $cached : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function forgetNodeMap(int $campaignId): void
    {
        try {
            $this->store()->forget($this->nodeMapKey($campaignId));
        } catch (\Throwable) {
        }
    }

    private function nodeMapKey(int $campaignId): string
    {
        return "drip:campaign:{$campaignId}:nodes";
    }

    private function store(): Repository
    {
        if ($this->store === null) {
            $path = storage_path('framework/cache/drip');

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
