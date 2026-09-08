<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Template Microservice Enabled Flag
    |--------------------------------------------------------------------------
    | When enabled, Template operations will be routed to the standalone
    | template-service microservice via TemplateServiceClient.
    */
    'enabled' => (bool) env('TEMPLATE_SERVICE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Template Microservice Base URL
    |--------------------------------------------------------------------------
    */
    'base_url' => rtrim((string) env('TEMPLATE_SERVICE_BASE_URL', 'http://127.0.0.1:8003/api/v1'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Service-to-Service Shared Secret Token
    |--------------------------------------------------------------------------
    */
    'token' => env('TEMPLATE_SERVICE_TOKEN', env('SERVICE_AUTH_TOKEN', 'default-template-service-secret-token')),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Timeout in Seconds
    |--------------------------------------------------------------------------
    */
    'timeout_seconds' => (int) env('TEMPLATE_SERVICE_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Graceful Fallback to Monolith Local Implementation
    |--------------------------------------------------------------------------
    | When true, if the template-service microservice is unreachable or returns
    | a 5xx error, the adapter falls back to the monolith's local domain service.
    */
    'fallback_to_local' => (bool) env('TEMPLATE_SERVICE_FALLBACK_TO_LOCAL', true),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Retries
    |--------------------------------------------------------------------------
    */
    'retry_attempts' => (int) env('TEMPLATE_SERVICE_RETRY_ATTEMPTS', 2),
    'retry_backoff_ms' => (int) env('TEMPLATE_SERVICE_RETRY_BACKOFF_MS', 100),
];
