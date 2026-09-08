<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Models;

use App\Models\TenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifySendData extends TenantModel
{
    use HasFactory;

    /** @return \Illuminate\Database\Eloquent\Factories\Factory<static> */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \Database\Factories\ShopifySendDataFactory::new();
    }
    protected $table = 'shopify_send_data';

    protected $fillable = [
        'user_id',
        'event_type',
        'payload',
        'whatsapp_number',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'  => 'array',
            'sent_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
