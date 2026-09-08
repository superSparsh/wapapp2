<?php

declare(strict_types=1);

return [
    'per_page' => (int) env('CAMPAIGN_PER_PAGE', 10),
    'sort_columns' => ['name', 'created_at', 'total_recipients', 'scheduled_at', 'status'],
    'dispatch_batch_size' => (int) env('CAMPAIGN_DISPATCH_BATCH_SIZE', 100),
    'queue' => env('CAMPAIGN_QUEUE', 'default'),
    /*
    | Pending recipients >= this → SendChatappMassMessage.
    | Below this → SendChatappMessage (simple) per recipient.
    */
    'mass_threshold' => (int) env('CAMPAIGN_MASS_THRESHOLD', 50),
    'mass_batch_size' => (int) env('CAMPAIGN_MASS_BATCH_SIZE', 1000),
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
