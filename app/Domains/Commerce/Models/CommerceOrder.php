<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Models;

use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Enums\PaymentStatus;
use App\Models\TenantModel;
use Database\Factories\CommerceOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceOrder extends TenantModel
{
    use HasFactory;

    protected $table = 'commerce_orders';

    /** @return \Illuminate\Database\Eloquent\Factories\Factory<static> */
    protected static function newFactory(): CommerceOrderFactory
    {
        return CommerceOrderFactory::new();
    }

    protected $fillable = [
        'catalog_id',
        'customer_name',
        'customer_phone',
        'product_items',
        'total_price',
        'currency',
        'order_status',
        'payment_status',
        'whatsapp_line_id',
        'external_message_id',
        'payment_link',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'product_items'  => 'array',
            'total_price'    => 'decimal:2',
            'order_status'   => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'metadata'       => 'array',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CommercePayment::class, 'commerce_order_id');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function hasPaymentLink(): bool
    {
        return filled($this->payment_link);
    }
}
