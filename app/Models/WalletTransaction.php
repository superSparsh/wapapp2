<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletTransactionType;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasPublicUuid;

    public $timestamps = false;

    protected $fillable = [
        'type',
        'amount',
        'currency',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'razorpay_payment_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
