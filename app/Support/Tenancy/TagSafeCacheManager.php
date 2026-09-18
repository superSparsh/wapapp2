<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Cache\CacheManager as BaseCacheManager;

/**
 * Stancl's cache manager always wraps calls in tags(). File and database
 * stores cannot tag, which crashes tenant requests (login → dashboard).
 *
 * Redis/Memcached keep tag isolation. Other stores get a tenant key prefix.
 */
class TagSafeCacheManager extends BaseCacheManager
{
    public function __call($method, $parameters)
    {
        $store = $this->store();
        $tenantTag = (string) config('tenancy.cache.tag_base', 'tenant').tenant()->getTenantKey();

        if ($store->supportsTags()) {
            if ($method === 'tags') {
                $names = (array) ($parameters[0] ?? []);

                return $store->tags(array_merge([$tenantTag], $names));
            }

            return $store->tags([$tenantTag])->$method(...$parameters);
        }

        if ($method === 'tags') {
            return $store;
        }

        return $store->$method(...$this->prefixParameters($method, $parameters, $tenantTag.':'));
    }

    /**
     * @param  array<int, mixed>  $parameters
     * @return array<int, mixed>
     */
    private function prefixParameters(string $method, array $parameters, string $prefix): array
    {
        if ($parameters === []) {
            return $parameters;
        }

        if (in_array($method, ['many', 'getMultiple', 'deleteMultiple'], true) && is_array($parameters[0] ?? null)) {
            $parameters[0] = array_map(
                fn (mixed $key): string => $prefix.(string) $key,
                $parameters[0],
            );

            return $parameters;
        }

        if (in_array($method, ['putMany', 'setMultiple'], true) && is_array($parameters[0] ?? null)) {
            $prefixed = [];
            foreach ($parameters[0] as $key => $value) {
                $prefixed[$prefix.(string) $key] = $value;
            }
            $parameters[0] = $prefixed;

            return $parameters;
        }

        if (is_string($parameters[0] ?? null)) {
            $parameters[0] = $prefix.$parameters[0];
        }

        return $parameters;
    }
}
