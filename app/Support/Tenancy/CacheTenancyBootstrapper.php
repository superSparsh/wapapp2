<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class CacheTenancyBootstrapper implements TenancyBootstrapper
{
    protected ?CacheManager $originalCache = null;

    public function __construct(
        protected Application $app,
    ) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->resetFacadeCache();

        $this->originalCache = $this->originalCache ?? $this->app['cache'];
        $this->app->extend('cache', function () {
            return new TagSafeCacheManager($this->app);
        });
    }

    public function revert(): void
    {
        $this->resetFacadeCache();

        $this->app->extend('cache', function () {
            return $this->originalCache;
        });

        $this->originalCache = null;
    }

    public function resetFacadeCache(): void
    {
        Cache::clearResolvedInstances();
    }
}
