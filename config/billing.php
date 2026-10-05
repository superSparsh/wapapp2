<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Razorpay (deprecated env fallback)
    |--------------------------------------------------------------------------
    |
    | Platform billing reads Razorpay credentials from Admin → Payment gateways
    | (platform_settings: payment.razorpay_*). These env values are unused at
    | runtime and kept only for reference / local docs.
    |
    */
    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    'gst_rate' => (float) env('BILLING_GST_RATE', 18),

    'wallet' => [
        'default_recharge_amount' => 5000,
        'min_recharge_amount' => 500,
        'max_recharge_amount' => 500000,
        'quick_recharge_amounts' => [10000, 15000, 20000],
        'history_per_page' => 25,
        'history_max_days' => (int) env('WALLET_HISTORY_MAX_DAYS', 365),
        // Hide opt-in debits from customer Wallet History; admins (impersonation / Admin View) still see them.
        'hide_opt_in_from_history' => (bool) env('WALLET_HIDE_OPT_IN_FROM_HISTORY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Free service messages (Meta-style)
    |--------------------------------------------------------------------------
    |
    | First N outbound SERVICE (session) messages per calendar month are free
    | for each WhatsApp business phone number (whatsapp_lines row). Templates
    | (marketing / utility / authentication) are always charged as usual.
    |
    */
    'service_free_messages_per_month' => (int) env('SERVICE_FREE_MESSAGES_PER_MONTH', 1000),

    'data_deletion' => [
        'modules' => [
            'campaigns' => 'Campaigns',
            'audiences' => 'Audiences',
            'inbox' => 'Inbox',
        ],
        'data_age_options' => [
            '1_month' => ['label' => 'Older than 1 month', 'days' => 30],
            '3_months' => ['label' => 'Older than 3 months', 'days' => 90],
            '6_months' => ['label' => 'Older than 6 months', 'days' => 180],
            '1_year' => ['label' => 'Older than 1 year', 'days' => 365],
        ],
        'schedule_options' => [
            '1_day' => ['label' => 'In 1 Day', 'days' => 1],
            '3_days' => ['label' => 'In 3 Days', 'days' => 3],
            '7_days' => ['label' => 'In 7 Days', 'days' => 7],
        ],
        'export_retention_days' => (int) env('DATA_EXPORT_RETENTION_DAYS', 30),
    ],

    'activity_log' => [
        'per_page' => 25,
        'scopes' => [
            'account' => 'Account',
            'billing' => 'Billing',
            'security' => 'Security',
        ],
    ],
];
