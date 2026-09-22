<?php

declare(strict_types=1);

/**
 * Operational email + WhatsApp alerts (owner business alerts, admin digests, developer ops).
 * Template codes should match Meta/CAMS templates synced on the platform WhatsApp line.
 */
return [

    'enabled' => (bool) env('OPERATIONAL_ALERTS_ENABLED', true),

    'queue' => env('OPERATIONAL_ALERTS_QUEUE', 'default'),

    /*
    | Platform WhatsApp sender (same Alibaba CAMS pattern as login OTP).
    */
    'whatsapp' => [
        'from' => env('OPERATIONAL_ALERTS_WA_FROM', env('LOGIN_OTP_WHATSAPP_FROM')),
        'cust_space_id' => env('OPERATIONAL_ALERTS_WA_CUST_SPACE_ID', env('LOGIN_OTP_WHATSAPP_CUST_SPACE_ID')),
        'language' => env('OPERATIONAL_ALERTS_WA_LANGUAGE', 'en_GB'),
    ],

    'alibaba' => [
        'access_key_id' => env('ALIBABA_ACCESS_KEY_ID'),
        'access_key_secret' => env('ALIBABA_ACCESS_KEY_SECRET'),
        'endpoint' => env('ALIBABA_ENDPOINT', 'cams.ap-southeast-1.aliyuncs.com'),
    ],

    /*
    | Admin / developer recipients — same env keys as legacy where possible.
    | Legacy: PLAN_EXPIRY_NOTIFICATION, WHATSAPP_TO_NUMBERS, WHATSAPP_TO_NUMBERS_PLAN_EXPIRATION
    */
    'admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'PLAN_EXPIRY_NOTIFICATION',
            env('OPERATIONAL_ALERTS_ADMIN_EMAILS', env('ACCOUNT_EXPIRATION_REPORT_EMAILS', ''))
        ))
    ))),

    'developer_whatsapp_numbers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'WHATSAPP_TO_NUMBERS',
            env('OPERATIONAL_ALERTS_DEVELOPER_WHATSAPP', '')
        ))
    ))),

    'admin_whatsapp_numbers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'WHATSAPP_TO_NUMBERS_PLAN_EXPIRATION',
            env('WHATSAPP_TO_NUMBERS', env('OPERATIONAL_ALERTS_DEVELOPER_WHATSAPP', ''))
        ))
    ))),

    'support' => [
        'phone' => env('OPERATIONAL_ALERTS_SUPPORT_PHONE', '+91 00000 00000'),
        'whatsapp_url' => env('OPERATIONAL_ALERTS_SUPPORT_WA_URL', 'https://wa.me/910000000000'),
        'email' => env('OPERATIONAL_ALERTS_SUPPORT_EMAIL', 'support@wapapp.in'),
    ],

    'plan_expiration' => [
        'reminder_days' => [30, 7, 3, 1],
        'whatsapp_template' => env('ALERT_WA_TEMPLATE_PLAN_EXPIRATION', 'plan_expiration_notification_wapapp'),
        'whatsapp_renewal_template' => env('ALERT_WA_TEMPLATE_PLAN_RENEWAL', 'plan_renewal_reminder_wapapp'),
    ],

    'inbox_new_message' => [
        'whatsapp_template' => env('ALERT_WA_TEMPLATE_INBOX', 'wapapp_inbox_notification'),
        'throttle_seconds' => (int) env('ALERT_INBOX_THROTTLE_SECONDS', 120),
    ],

    'low_wallet' => [
        'threshold' => (float) env('ALERT_LOW_WALLET_THRESHOLD', 100),
        'whatsapp_template' => env('ALERT_WA_TEMPLATE_LOW_WALLET', 'wallet_low_balance_wapapp'),
        'notify_developers' => (bool) env('ALERT_LOW_WALLET_NOTIFY_DEVELOPERS', true),
        'throttle_hours' => (int) env('ALERT_LOW_WALLET_THROTTLE_HOURS', 12),
    ],

    'phone_quality' => [
        'whatsapp_template' => env('ALERT_WA_TEMPLATE_PHONE_QUALITY', 'phone_quality_change_wapapp'),
    ],

    'error' => [
        'whatsapp_template' => env('ALERT_WA_TEMPLATE_ERROR', 'error_template_wapapp'),
    ],

    'calendly' => [
        'customer_created' => env('ALERT_WA_TEMPLATE_CALENDLY_CUSTOMER_CREATED', 'calendly_customer_invite_created'),
        'customer_canceled' => env('ALERT_WA_TEMPLATE_CALENDLY_CUSTOMER_CANCELED', 'calendly_customer_invite_canceled'),
        'customer_reminder' => env('ALERT_WA_TEMPLATE_CALENDLY_CUSTOMER_REMINDER', 'calendly_customer_event_reminder'),
        'admin_created' => env('ALERT_WA_TEMPLATE_CALENDLY_ADMIN_CREATED', 'calendly_user_invite_created'),
        'admin_canceled' => env('ALERT_WA_TEMPLATE_CALENDLY_ADMIN_CANCELED', 'calendly_user_invite_canceled'),
        'admin_reminder' => env('ALERT_WA_TEMPLATE_CALENDLY_ADMIN_REMINDER', 'calendly_user_event_reminder'),
    ],

    'google_calendar' => [
        'customer_created' => env('ALERT_WA_TEMPLATE_GCAL_CUSTOMER_CREATED', 'google_calendar_customer_event_created'),
        'customer_canceled' => env('ALERT_WA_TEMPLATE_GCAL_CUSTOMER_CANCELED', 'google_calendar_customer_event_canceled'),
        'customer_reminder' => env('ALERT_WA_TEMPLATE_GCAL_CUSTOMER_REMINDER', 'google_calendar_customer_event_reminder'),
        'admin_created' => env('ALERT_WA_TEMPLATE_GCAL_ADMIN_CREATED', 'google_calendar_user_event_created'),
        'admin_canceled' => env('ALERT_WA_TEMPLATE_GCAL_ADMIN_CANCELED', 'google_calendar_user_event_canceled'),
        'admin_reminder' => env('ALERT_WA_TEMPLATE_GCAL_ADMIN_REMINDER', 'google_calendar_user_event_reminder'),
    ],

    'feature_request' => [
        'whatsapp_template' => env('ALERT_WA_TEMPLATE_FEATURE_REQUEST', 'customer_new_feature_request_wapapp'),
    ],

    'readiness' => [
        'admin_emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ALERT_READINESS_ADMIN_EMAILS', env('OPERATIONAL_ALERTS_ADMIN_EMAILS', '')))
        ))),
    ],
];
