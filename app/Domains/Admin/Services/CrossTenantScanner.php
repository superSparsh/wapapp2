<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Models\Tenant;
use Illuminate\Support\Collection;
use Throwable;

class CrossTenantScanner
{
    /**
     * Run a callback inside each tenant DB and collect results.
     *
     * @param  callable(Tenant): (array<int, array<string, mixed>>|Collection<int, array<string, mixed>>)  $callback
     * @return Collection<int, array<string, mixed>>
     */
    public function map(callable $callback, ?string $tenantId = null, int $tenantLimit = 200): Collection
    {
        $query = Tenant::query()->orderBy('id');
        if ($tenantId !== null && $tenantId !== '') {
            $query->whereKey($tenantId);
        } else {
            $query->limit($tenantLimit);
        }

        $rows = collect();
        $wasInitialized = tenancy()->initialized;
        $previous = $wasInitialized ? tenant() : null;

        if ($wasInitialized) {
            tenancy()->end();
        }

        foreach ($query->get() as $tenant) {
            try {
                tenancy()->initialize($tenant);
                $chunk = $callback($tenant);
                foreach ($chunk as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $item['tenant_id'] = (string) $tenant->id;
                    $item['tenant_name'] = (string) ($tenant->company_name ?: $tenant->name);
                    $rows->push($item);
                }
            } catch (Throwable $e) {
                report($e);
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        if ($previous !== null) {
            tenancy()->initialize($previous);
        }

        return $rows;
    }
}
