<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Tenancy\Services\TenantDatabaseNamingService;
use App\Enums\TenantStatus;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    protected $keyType = 'string';

    public $incrementing = false;

    public function getIncrementing(): bool
    {
        return false;
    }

    public function shouldGenerateId(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'database_name',
            'name',
            'company_name',
            'email',
            'phone',
            'status',
            'plan_id',
            'timezone',
            'locale',
            'country_code',
            'settings',
            'provisioned_at',
            'suspended_at',
        ];
    }

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'settings' => 'array',
            'provisioned_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if (filled($tenant->id) && blank($tenant->database_name)) {
                $tenant->database_name = app(TenantDatabaseNamingService::class)->forTenantId($tenant->id);
            }
        });
    }
}
