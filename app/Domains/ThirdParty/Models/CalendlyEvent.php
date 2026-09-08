<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Models;

use App\Domains\ThirdParty\Enums\EventStatus;
use App\Models\TenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendlyEvent extends TenantModel
{
    protected $table = 'calendly_events';

    protected $fillable = [
        'user_id',
        'event_id',
        'status',
        'event_type',
        'start_time',
        'end_time',
        'invitee_email',
        'event_uri',
        'raw_payload',
        'whatsapp_number',
        'form_responses',
        'notified_created',
        'notified_canceled',
        'notified_rescheduled',
    ];

    protected function casts(): array
    {
        return [
            'status'               => EventStatus::class,
            'start_time'           => 'datetime',
            'end_time'             => 'datetime',
            'raw_payload'          => 'array',
            'form_responses'       => 'array',
            'notified_created'     => 'boolean',
            'notified_canceled'    => 'boolean',
            'notified_rescheduled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === EventStatus::Active;
    }

    public function isCanceled(): bool
    {
        return $this->status === EventStatus::Canceled;
    }
}
