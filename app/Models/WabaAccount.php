<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WabaAccount extends TenantModel
{
    protected $fillable = [
        'user_id',
        'waba_id',
        'alibaba_cust_space_id',
        'is_registered',
        'waba_response',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_registered' => 'boolean',
            'waba_response' => 'array',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
