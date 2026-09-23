<?php

declare(strict_types=1);

return [
    'per_page' => 10,
    'default_timezone' => 'Asia/Kolkata',
    'max_campaigns_per_tenant' => 100,
    'max_recipients_per_campaign' => 50000,
    'sort_columns' => ['name', 'created_at', 'total_recipients', 'scheduled_at', 'status'],
    'queue' => env('CAMPAIGN_QUEUE', 'campaign'),
    'dispatch_batch_size' => 100,
    /*
    | Mass SendChatappMassMessage is disabled until CAMS mass API is tested.
    | All campaigns use per-recipient simple SendChatappMessage jobs.
    */
    'mass_threshold' => (int) env('CAMPAIGN_MASS_THRESHOLD', PHP_INT_MAX),
    'mass_batch_size' => (int) env('CAMPAIGN_MASS_BATCH_SIZE', 1000),
    'mass_api_enabled' => (bool) env('CAMPAIGN_MASS_API_ENABLED', false),
    /*
    | Meta Oct 1 2026: charge service (session) messages + utility in 24h CSW.
    | Before this date, utility 24h skip still applies and free-form is not charged.
    */
    'meta_service_billing_starts_at' => env('META_SERVICE_BILLING_STARTS_AT', '2026-10-01'),
    'cost' => [
        'currency' => 'INR',
        /** Used when admin CountryPricing row is missing. */
        'category_rates' => [
            'MARKETING' => 0.88,
            'UTILITY' => 0.35,
            'AUTHENTICATION' => 0.35,
            'SERVICE' => 0.35, // Meta: service rates match utility/auth
            'DEFAULT' => 0.78,
        ],
        'default_country_code' => env('CAMPAIGN_COST_COUNTRY', 'IN'),
        'fallback_conversion_price' => env('WALLET_CONVERSION_PRICE', '83.17'),
    ],
];
