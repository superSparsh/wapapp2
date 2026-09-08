<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Models;

use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Models\TenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleCalendarIntegration extends TenantModel
{
    protected $table = 'google_calendar_integrations';

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
        return $this->hasMany(GoogleCalendarEvent::class, 'user_id', 'user_id');
    }

    public function bookingLinks(): HasMany
    {
        return $this->hasMany(GoogleCalendarBookingLink::class, 'user_id', 'user_id');
    }

    public function isEnabled(): bool
    {
        return $this->status === IntegrationStatus::Enabled;
    }

    public function hasRefreshToken(): bool
    {
        return filled($this->settings['refresh_token'] ?? null);
    }

    public function calendarId(): string
    {
        return (string) ($this->settings['calendar_id'] ?? 'primary');
    }

    public function isMeetOnly(): bool
    {
        return (bool) ($this->settings['meet_only'] ?? false);
    }

    public function reminderMinutes(): int
    {
        return (int) ($this->settings['reminder_minutes_before'] ?? 30);
    }
}
