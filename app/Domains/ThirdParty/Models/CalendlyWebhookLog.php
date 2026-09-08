<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Models;

use App\Models\TenantModel;

class CalendlyWebhookLog extends TenantModel
{
    protected $table = 'calendly_webhook_logs';

    protected $fillable = [
        'payload',
        'processed',
    ];

    protected function casts(): array
    {
        return [
            'payload'   => 'array',
            'processed' => 'boolean',
        ];
    }
}
