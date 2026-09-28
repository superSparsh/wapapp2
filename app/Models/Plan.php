<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingCycle;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasPublicUuid;
    use SoftDeletes;
    use UsesCentralConnection;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'price',
        'starting_wallet_balance',
        'currency',
        'billing_cycle',
        'validity_days',
        'messages_limit',
        'contacts_limit',
        'team_members_limit',
        'whatsapp_lines_limit',
        'sort_order',
        'is_active',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'starting_wallet_balance' => 'decimal:2',
            'billing_cycle' => BillingCycle::class,
            'validity_days' => 'integer',
            'is_active' => 'boolean',
            'features' => 'array',
        ];
    }

    public function resolvedValidityDays(): int
    {
        $days = (int) ($this->validity_days ?? 0);
        if ($days > 0) {
            return $days;
        }

        $cycle = $this->billing_cycle instanceof BillingCycle
            ? $this->billing_cycle
            : BillingCycle::tryFrom((string) $this->billing_cycle) ?? BillingCycle::Monthly;

        return $cycle->defaultValidityDays();
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
