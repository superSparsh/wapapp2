<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantUserAccountType;
use App\Models\Concerns\UsesCentralConnection;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUserAccess extends Model
{
    use UsesCentralConnection;

    protected $table = 'tenant_user_access';

    protected $fillable = [
        'email',
        'phone',
        'tenant_id',
        'account_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => TenantUserAccountType::class,
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function findActiveByEmail(string $email): ?self
    {
        return static::query()
            ->where('email', strtolower($email))
            ->where('is_active', true)
            ->first();
    }

    public static function findActiveByPhone(string $phone): ?self
    {
        foreach (PhoneNormalizer::lookupVariants($phone) as $variant) {
            $access = static::query()
                ->where('phone', $variant)
                ->where('is_active', true)
                ->first();

            if ($access !== null) {
                return $access;
            }
        }

        return null;
    }
}
