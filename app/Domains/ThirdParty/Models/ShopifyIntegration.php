<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Models;

use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Models\TenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyIntegration extends TenantModel
{
    protected $table = 'shopify_integrations';

    protected $fillable = [
        'user_id',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'status'   => IntegrationStatus::class,
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEnabled(): bool
    {
        return $this->status === IntegrationStatus::Enabled;
    }

    /** @return string|null */
    public function domainUrl(): ?string
    {
        return $this->settings['shopifydomainurl'] ?? null;
    }
}
