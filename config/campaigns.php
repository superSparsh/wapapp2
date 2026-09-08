<?php

declare(strict_types=1);

return [
    'per_page' => 10,
    'default_timezone' => 'Asia/Kolkata',
    'max_campaigns_per_tenant' => 100,
    'max_recipients_per_campaign' => 50000,
    'sort_columns' => ['name', 'created_at', 'total_recipients', 'scheduled_at', 'status'],
    'queue' => env('CAMPAIGN_QUEUE', 'default'),
    'dispatch_batch_size' => 100,
    /*
    | Recipients at/above this count use CAMS SendChatappMassMessage.
    | Below it, each recipient uses SendChatappMessage (simple API).
    */
    'mass_threshold' => (int) env('CAMPAIGN_MASS_THRESHOLD', 50),
    'mass_batch_size' => (int) env('CAMPAIGN_MASS_BATCH_SIZE', 1000),
    'cost' => [
        'currency' => 'INR',
        'category_rates' => [
            'MARKETING' => 0.88,
            'UTILITY' => 0.35,
            'AUTHENTICATION' => 0.35,
            'DEFAULT' => 0.78,
        ],
    ],
];
