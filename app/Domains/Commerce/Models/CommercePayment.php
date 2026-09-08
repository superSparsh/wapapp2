<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Models;

use App\Domains\Commerce\Enums\PaymentLinkStatus;
use App\Models\TenantModel;
use Database\Factories\CommercePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercePayment extends TenantModel
{
    use HasFactory;

    protected $table = 'commerce_payments';

    /** @return \Illuminate\Database\Eloquent\Factories\Factory<static> */
    protected static function newFactory(): CommercePaymentFactory
    {
        return CommercePaymentFactory::new();
    }

    protected $fillable = [
        'commerce_order_id',
        'internal_order_ref',
        'customer_name',
        'customer_phone',
        'amount',
        'currency',
        'razorpay_payment_link_id',
        'payment_link',
        'status',
        'razorpay_payment_id',
        'paid_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount'   => 'decimal:2',
            'status'   => PaymentLinkStatus::class,
            'paid_at'  => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CommerceOrder::class, 'commerce_order_id');
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentLinkStatus::Paid;
    }

    public function isExpired(): bool
    {
        return $this->status === PaymentLinkStatus::Expired
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }
}
