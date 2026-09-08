<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;

class RazorpayOrder extends TenantModel
{
    protected $fillable = [
        'razorpay_order_id',
        'razorpay_payment_id',
        'purpose',
        'amount',
        'tax_amount',
        'total_amount',
        'currency',
        'status',
        'plan_id',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => RazorpayOrderPurpose::class,
            'status' => RazorpayOrderStatus::class,
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }
}
