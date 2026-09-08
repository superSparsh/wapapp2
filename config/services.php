<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    
    'shopify' => [
        'api_key' => env('SHOPIFY_API_KEY'),
        'api_secret' => env('SHOPIFY_API_SECRET'),
        'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
        'redirect_uri' => env('SHOPIFY_REDIRECT_URI'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_OAUTH_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_OAUTH_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],
     'wa_health' => [
        'alert_webhook_url' => env('WA_HEALTH_ALERT_WEBHOOK_URL'),
        /** Comma-separated recipients for WhatsApp Health Center digest emails */
        'digest_emails' => env('WA_HEALTH_DIGEST_EMAILS', 'tittu@tekprocloud.com'),
        /** Internal/test accounts excluded from lowest-usage fleet rankings */
        'performance_excluded_emails' => [
            'thakursparsh.st@gmail.com',
            'fff@fdd.fff',
            'tekpro@tekpro.com',
            'integration-testing@zapier.com',
            'shaikhsohelnm@gmail.com',
            '21801063sohel@viva-technology.org',
            'sohel.shaikh@tekkonnectpro.com',
            'manoj.kumar@tekkonnectpro.com',
            'koromanoj@abc.com',
            'rajat96mehta@abc.com',
            'vivian@tekkonnectpro.com',
            'vincent.dsouza@madvr.in',
            'wadmin@tittu.in',
        ],
    ],

    /** Comma-separated recipients for monthly account expiration report (falls back to renewal/admin notification emails). */
    'account_expiration' => [
        'report_emails' => env('ACCOUNT_EXPIRATION_REPORT_EMAILS'),
    ],

    'whatsapp_meta_pricing' => [
        /** Optional override when Meta CDN URL rotates; leave empty to auto-discover from developers.facebook.com */
        'usd_csv_url' => env('META_USD_PRICING_CSV_URL'),
    ],

    /** customers.wallet_amount: inr (legacy) or usd — see wallet:migrate-balance-to-usd */
    'wallet_balance_unit' => env('WALLET_BALANCE_UNIT', 'inr'),

    /** Show “≈ ₹…” conversion hints on campaigns / wallet credit preview (default: hidden). */
    'wallet_show_inr_conversion_hints' => env('WALLET_SHOW_INR_CONVERSION_HINTS', false),

    /** Default UI currency when session has no wallet_display_currency yet (USD or INR). */
    'wallet_display_currency_default' => env('WALLET_DISPLAY_CURRENCY_DEFAULT', 'INR'),
];
