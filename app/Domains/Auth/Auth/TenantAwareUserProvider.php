<?php

declare(strict_types=1);

namespace App\Domains\Auth\Auth;

use App\Domains\Auth\Services\TenantResolver;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class TenantAwareUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        if (! $this->ensureTenantContext()) {
            return null;
        }

        return parent::retrieveById($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        if (! $this->ensureTenantContext()) {
            return null;
        }

        return parent::retrieveByToken($identifier, $token);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (! $this->ensureTenantContext()) {
            return null;
        }

        return parent::retrieveByCredentials($credentials);
    }

    protected function ensureTenantContext(): bool
    {
        if (tenancy()->initialized) {
            return true;
        }

        app(TenantResolver::class)->initializeFromSession();

        return tenancy()->initialized;
    }
}
