<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Support\AuthSession;

class SignupSessionService
{
    /** @return array<string, mixed> */
    public function all(): array
    {
        return session(AuthSession::SIGNUP, []);
    }

    /** @param  array<string, mixed>  $data */
    public function merge(array $data): void
    {
        session([AuthSession::SIGNUP => array_merge($this->all(), $data)]);
    }

    public function forget(): void
    {
        session()->forget(AuthSession::SIGNUP);
    }

    public function hasCompletedPreRegistration(): bool
    {
        $required = ['meta_access', 'has_website', 'business_terms', 'commerce_policy', 'manager_verified'];

        foreach ($required as $key) {
            if (($this->all()[$key] ?? null) !== 'yes') {
                return false;
            }
        }

        return true;
    }
}
