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
        $existing = BillingAddress::query()
            ->where('is_default', true)
            ->latest('id')
            ->first()
            ?? BillingAddress::query()->latest('id')->first();

        if ($existing) {
            BillingAddress::query()
                ->where('id', '!=', $existing->id)
                ->update(['is_default' => false]);

            $existing->update(array_merge($data, ['is_default' => true]));

            return $existing->fresh() ?? $existing;
        }

        return BillingAddress::query()->create(array_merge($data, ['is_default' => true]));
    }
}
