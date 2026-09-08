<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Models\BillingAddress;

class BillingAddressService
{
    public function default(): ?BillingAddress
    {
        return BillingAddress::query()
            ->where('is_default', true)
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): BillingAddress
    {
        BillingAddress::query()->update(['is_default' => false]);

        $existing = $this->default();

        if ($existing) {
            $existing->update(array_merge($data, ['is_default' => true]));

            return $existing->fresh();
        }

        return BillingAddress::query()->create(array_merge($data, ['is_default' => true]));
    }
}
