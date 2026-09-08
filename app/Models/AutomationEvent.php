<?php

declare(strict_types=1);

namespace App\Models;

class AutomationEvent extends TenantModel
{
    protected $fillable = [
        'name',
        'status',
        'event_type',
        'payload',
        'scheduled_at',
        'processed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'scheduled_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
