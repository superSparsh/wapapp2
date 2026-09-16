<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountPreference extends Model
{
    protected $fillable = [
        'alerts_enabled',
        'notifications_read_at',
    ];

    protected function casts(): array
    {
        return [
            'alerts_enabled' => 'boolean',
            'notifications_read_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'alerts_enabled' => false,
            'notifications_read_at' => null,
        ]);
    }
}
