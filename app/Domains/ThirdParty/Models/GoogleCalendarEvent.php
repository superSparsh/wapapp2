<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Models;

use App\Domains\ThirdParty\Enums\EventStatus;
use App\Models\TenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarEvent extends TenantModel
{
    protected $table = 'google_calendar_events';

    protected $fillable = [
        'user_id',
        'event_id',
        'calendar_id',
        'status',
        'summary',
        'start_time',
        'end_time',
        'invitee_email',
        'invitee_name',
        'meet_link',
        'calendar_link',
        'whatsapp_number',
        'raw_payload',
        'attendee_payload',
        'notified_created',
        'notified_canceled',
        'notified_rescheduled',
        'reminder_days_sent',
        'previous_start_time',
    ];

    protected function casts(): array
    {
        return [
            'status'               => EventStatus::class,
            'start_time'           => 'datetime',
            'end_time'             => 'datetime',
            'previous_start_time'  => 'datetime',
            'raw_payload'          => 'array',
            'attendee_payload'     => 'array',
            'reminder_days_sent'   => 'array',
            'notified_created'     => 'boolean',
            'notified_canceled'    => 'boolean',
            'notified_rescheduled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasMeetLink(): bool
    {
        return filled($this->meet_link);
    }

    public function isActive(): bool
    {
        return $this->status === EventStatus::Active;
    }

    public function isRescheduled(): bool
    {
        return $this->status === EventStatus::Rescheduled;
    }
}
