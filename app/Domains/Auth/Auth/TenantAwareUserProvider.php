<?php

declare(strict_types=1);

namespace App\Domains\Auth\Auth;

use App\Domains\Auth\Services\TenantResolver;
use App\Domains\Auth\Support\AuthSession;
use App\Domains\Auth\Support\RememberTenantCookie;
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
        if (! $this->ensureTenantContext(fromRememberCookie: true)) {
            return null;
        }

        $user = parent::retrieveByToken($identifier, $token);

        if ($user === null) {
            RememberTenantCookie::forget();

            return null;
        }

        $remembered = RememberTenantCookie::read();
        if ($remembered !== null) {
            app(TenantResolver::class)->storeInSession($remembered['tenant_id'], $remembered['guard']);
            session([AuthSession::TWO_FACTOR_VERIFIED => true]);
        }

        return $user;
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (! $this->ensureTenantContext()) {
            return null;
        }

        return parent::retrieveByCredentials($credentials);
    }

    protected function ensureTenantContext(bool $fromRememberCookie = false): bool
    {
        if (tenancy()->initialized) {
            return true;
        }

        app(TenantResolver::class)->initializeFromSession();

        if (tenancy()->initialized) {
            return true;
        }

        if (! $fromRememberCookie) {
            return false;
        }

        return app(TenantResolver::class)->initializeFromRememberCookie();
    }
}
