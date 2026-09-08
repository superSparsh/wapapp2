<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;

class Subscription extends TenantModel
{
    protected $fillable = [
        'plan_id',
        'status',
        'amount',
        'currency',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'razorpay_subscription_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
