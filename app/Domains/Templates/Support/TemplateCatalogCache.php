<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use Illuminate\Support\Facades\Cache;

final class TemplateCatalogCache
{
    /** @var array<string, list<array<string, string>>> */
    private static array $memo = [];

    /**
     * @param  callable(): list<array<string, string>>  $resolver
     * @return list<array<string, string>>
     */
    public function remember(int $lineId, callable $resolver): array
    {
        $key = 'template-catalog:'.$lineId;

        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $ttl = (int) config('templates.catalog_cache_seconds', 300);

        if ($ttl <= 0) {
            return self::$memo[$key] = $resolver();
        }

        return self::$memo[$key] = Cache::remember($key, $ttl, $resolver);
    }

    public static function flush(): void
    {
        self::$memo = [];
    }
}
