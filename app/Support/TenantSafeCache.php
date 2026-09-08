<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;

/**
 * Cache helpers that stay safe under Stancl tenancy when the default
 * store is file/database (those drivers do not support cache tags).
 *
 * CacheTenancyBootstrapper wraps Cache::__call() with tenant tags, so
 * Cache::remember() blows up. Calling a concrete store(), or a private
 * FileStore, avoids that wrapper.
 */
final class TenantSafeCache
{
    private static ?Repository $fileRepository = null;

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    public static function remember(string $key, int|\DateInterval|\DateTimeInterface $ttl, callable $callback): mixed
    {
        try {
            return Cache::store('file')->remember($key, $ttl, $callback);
        } catch (\BadMethodCallException) {
            return self::fileRepository()->remember($key, $ttl, $callback);
        }
    }

    public static function forget(string $key): void
    {
        try {
            Cache::store('file')->forget($key);
        } catch (\BadMethodCallException) {
            self::fileRepository()->forget($key);
        }
    }

    private static function fileRepository(): Repository
    {
        if (self::$fileRepository === null) {
            $path = storage_path('framework/cache/tenant-safe');

            if (! is_dir($path)) {
                @mkdir($path, 0755, true);
            }

            self::$fileRepository = new Repository(
                new FileStore(new Filesystem(), $path),
            );
        }

        return self::$fileRepository;
    }
}
