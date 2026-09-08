<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Models;

use App\Models\TenantModel;

class GoogleCalendarWebhookLog extends TenantModel
{
    protected $table = 'google_calendar_webhook_logs';

    protected $fillable = [
        'user_id',
        'headers',
        'payload',
        'processed',
    ];

    protected function casts(): array
    {
        return [
            'headers'   => 'array',
            'payload'   => 'array',
            'processed' => 'boolean',
        ];
    }
}
