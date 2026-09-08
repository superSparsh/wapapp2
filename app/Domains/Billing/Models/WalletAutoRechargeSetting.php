<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Models\TenantModel;

class WalletAutoRechargeSetting extends TenantModel
{
    protected $table = 'wallet_auto_recharge_settings';

    protected $fillable = [
        'enabled',
        'threshold_amount',
        'recharge_amount',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'threshold_amount' => 'decimal:2',
            'recharge_amount' => 'decimal:2',
            'last_triggered_at' => 'datetime',
        ];
    }
}
