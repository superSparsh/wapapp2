<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (HTTP v1)
    |--------------------------------------------------------------------------
    |
    | Completely optional. When disabled or credentials are missing, all FCM
    | calls become no-ops so inbox / webhooks never break.
    |
    */
    'enabled' => (bool) env('FCM_ENABLED', true),

    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),

    /** Optional override; otherwise read from the service-account JSON. */
    'project_id' => env('FIREBASE_PROJECT_ID'),

    'timeout_seconds' => (int) env('FCM_TIMEOUT_SECONDS', 8),

    'access_token_cache_key' => 'fcm.access_token',

    'access_token_ttl_seconds' => (int) env('FCM_ACCESS_TOKEN_TTL', 3000),
];
