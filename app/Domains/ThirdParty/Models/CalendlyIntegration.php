<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Models;

use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Models\TenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendlyIntegration extends TenantModel
{
    protected $table = 'calendly_integrations';

    protected $fillable = [
        'user_id',
        'status',
        'settings',
        'first_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status'          => IntegrationStatus::class,
            'settings'        => 'array',
            'first_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendlyEvent::class, 'user_id', 'user_id');
    }

    public function isEnabled(): bool
    {
        return $this->status === IntegrationStatus::Enabled;
    }

    public function hasAccessToken(): bool
    {
        return filled($this->settings['access_token'] ?? null);
    }

    public function accessToken(): ?string
    {
        return $this->settings['access_token'] ?? null;
    }
}
