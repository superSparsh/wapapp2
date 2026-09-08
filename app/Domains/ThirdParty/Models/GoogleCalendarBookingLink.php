<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Models;

use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Models\TenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarBookingLink extends TenantModel
{
    protected $table = 'google_calendar_booking_links';

    protected $fillable = [
        'user_id',
        'slug',
        'title',
        'duration_minutes',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'status'           => IntegrationStatus::class,
            'settings'         => 'array',
            'duration_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPublicUrlAttribute(): string
    {
        return route('google-calendar.booking.show', $this->slug);
    }

    public function isEnabled(): bool
    {
        return $this->status === IntegrationStatus::Enabled;
    }
}
