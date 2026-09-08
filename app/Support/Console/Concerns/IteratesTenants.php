<?php

declare(strict_types=1);

namespace App\Support\Console\Concerns;

use App\Models\Tenant;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

trait IteratesTenants
{
    /**
     * @param  Closure(Tenant): void  $callback
     */
    protected function foreachTenant(Closure $callback): void
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = $this->resolveTenants();

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            try {
                $callback($tenant);
            } finally {
                tenancy()->end();
            }
        }
    }

    /** @return Collection<int, Tenant> */
    protected function resolveTenants(): Collection
    {
        $ids = $this->option('tenants');

        if (is_array($ids) && $ids !== []) {
            return Tenant::query()->whereIn('id', $ids)->get();
        }

        return Tenant::query()->get();
    }
}
