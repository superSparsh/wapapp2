<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountPreference extends Model
{
    protected $fillable = [
        'alerts_enabled',
    ];

    protected function casts(): array
    {
        return [
            'alerts_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['alerts_enabled' => false]);
    }
}
