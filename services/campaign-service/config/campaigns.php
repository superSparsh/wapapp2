<?php

declare(strict_types=1);

return [
    'per_page' => (int) env('CAMPAIGN_PER_PAGE', 10),
    'sort_columns' => ['name', 'created_at', 'total_recipients', 'scheduled_at', 'status'],
    'dispatch_batch_size' => (int) env('CAMPAIGN_DISPATCH_BATCH_SIZE', 100),
    'queue' => env('CAMPAIGN_QUEUE', 'default'),
    /*
    | Mass SendChatappMassMessage is disabled until CAMS mass API is tested.
    | All campaigns use per-recipient simple SendChatappMessage jobs.
    */
    'mass_threshold' => (int) env('CAMPAIGN_MASS_THRESHOLD', PHP_INT_MAX),
    'mass_batch_size' => (int) env('CAMPAIGN_MASS_BATCH_SIZE', 1000),
    'mass_api_enabled' => (bool) env('CAMPAIGN_MASS_API_ENABLED', false),
    'cost' => [
        'currency' => env('CAMPAIGN_COST_CURRENCY', 'INR'),
        'category_rates' => [
            'MARKETING' => (float) env('CAMPAIGN_RATE_MARKETING', 0.78),
            'UTILITY' => (float) env('CAMPAIGN_RATE_UTILITY', 0.35),
            'AUTHENTICATION' => (float) env('CAMPAIGN_RATE_AUTHENTICATION', 0.15),
            'SERVICE' => (float) env('CAMPAIGN_RATE_SERVICE', 0.25),
            'DEFAULT' => 0.78,
        ],
    ],
    'inbox_service' => [
        'url' => env('INBOX_SERVICE_URL', 'http://127.0.0.1:8001/api/v1'),
        'token' => env('INBOX_SERVICE_TOKEN', 'default-inbox-service-secret-token'),
        'timeout' => (int) env('INBOX_SERVICE_TIMEOUT', 5),
    ],
];
