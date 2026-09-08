<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Services;

use App\Models\Tenant;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Stancl\Tenancy\Database\Models\Domain;

class TenantSlugService
{
    public function generateUnique(string $name): string
    {
        $base = $this->normalizeBase($name);

        do {
            $suffix = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $tenantId = $base.'-'.$suffix;
        } while ($this->isTaken($tenantId));

        return $tenantId;
    }

    public function fromBaseAndSuffix(string $base, string $suffix): string
    {
        $tenantId = $this->normalizeBase($base).'-'.str_pad($suffix, 4, '0', STR_PAD_LEFT);

        $this->validate($tenantId);

        return $tenantId;
    }

    public function normalizeBase(string $value): string
    {
        $slug = Str::slug(Str::limit(trim($value), 32, ''));

        if ($slug === '') {
            $slug = 'tenant';
        }

        $maxBaseLength = 58 - 1 - 4;

        if (strlen($slug) > $maxBaseLength) {
            $slug = substr($slug, 0, $maxBaseLength);
            $slug = rtrim($slug, '-');
        }

        if ($this->isReserved($slug)) {
            $slug = $slug.'-co';
        }

        return $slug;
    }

    public function validate(string $tenantId): void
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $tenantId)) {
            throw new InvalidArgumentException('Invalid tenant identifier.');
        }

        if ($this->isReserved($tenantId)) {
            throw new InvalidArgumentException('This workspace name is reserved. Please choose another.');
        }

        if ($this->isTaken($tenantId)) {
            throw new InvalidArgumentException('This workspace name is already taken.');
        }
    }

    public function isReserved(string $slug): bool
    {
        return in_array($slug, config('tenancy.reserved_slugs', []), true);
    }

    public function isTaken(string $slug): bool
    {
        return Tenant::query()->whereKey($slug)->exists()
            || Domain::query()->where('domain', $slug)->exists();
    }
}
