<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Services;

use App\Domains\ThirdParty\Models\WooCommerceStore;
use Illuminate\Support\Collection;

class WooCommerceService
{
    public function all(): Collection
    {
        return WooCommerceStore::query()->latest('id')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): WooCommerceStore
    {
        return WooCommerceStore::query()->create([
            'store_url' => (string) $data['store_url'],
            'consumer_key' => $data['consumer_key'] ?? null,
            'consumer_secret' => $data['consumer_secret'] ?? null,
            'status' => (string) ($data['status'] ?? 'inactive'),
            'settings' => (array) ($data['settings'] ?? []),
        ]);
    }

    public function destroy(WooCommerceStore $store): void
    {
        $store->delete();
    }
}
