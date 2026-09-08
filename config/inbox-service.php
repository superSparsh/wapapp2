<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Inbox Microservice Configuration
    |--------------------------------------------------------------------------
    |
    | When enabled, Inbox requests will be routed to the standalone Inbox
    | microservice. If the microservice is unreachable and fallback_to_local
    | is true, requests gracefully fall back to the monolith local database.
    |
    */

    'enabled' => (bool) env('INBOX_SERVICE_ENABLED', false),

    'base_url' => rtrim((string) env('INBOX_SERVICE_BASE_URL', 'http://127.0.0.1:8001/api/v1'), '/'),

    'token' => env('INBOX_SERVICE_TOKEN', env('SERVICE_AUTH_TOKEN', 'default-inbox-service-secret-token')),

    'timeout_seconds' => (int) env('INBOX_SERVICE_TIMEOUT', 5),

    'fallback_to_local' => (bool) env('INBOX_SERVICE_FALLBACK_TO_LOCAL', true),

    'retry_attempts' => (int) env('INBOX_SERVICE_RETRY_ATTEMPTS', 2),

    'retry_backoff_ms' => (int) env('INBOX_SERVICE_RETRY_BACKOFF_MS', 100),
];
