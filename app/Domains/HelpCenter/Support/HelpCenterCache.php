<?php

declare(strict_types=1);

namespace App\Domains\HelpCenter\Support;

class HelpCenterCache
{
    /** @var array<string, mixed> */
    private static array $memo = [];

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    public static function remember(string $key, callable $callback): mixed
    {
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        return self::$memo[$key] = tenancy()->central($callback);
    }

    public static function flush(): void
    {
        self::$memo = [];
    }
}
