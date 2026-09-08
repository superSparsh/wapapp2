<?php

declare(strict_types=1);

namespace App\Domains\Audience\Models;

use App\Models\TenantModel;
use Database\Factories\BlacklistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Blacklist extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): BlacklistFactory
    {
        return BlacklistFactory::new();
    }

    protected $fillable = [
        'phone',
        'email',
        'reason',
    ];

    /**
     * Check if a phone or email is blacklisted.
     */
    public static function isBlacklisted(?string $phone, ?string $email): bool
    {
        return static::query()
            ->where(function ($query) use ($phone, $email): void {
                if ($phone) {
                    $query->orWhere('phone', $phone);
                }
                if ($email) {
                    $query->orWhere('email', $email);
                }
            })
            ->exists();
    }
}
