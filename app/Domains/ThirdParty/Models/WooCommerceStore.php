<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Models;

use App\Models\TenantModel;

class WooCommerceStore extends TenantModel
{
    protected $table = 'woo_commerce_stores';

    protected $fillable = [
        'store_url',
        'consumer_key',
        'consumer_secret',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
