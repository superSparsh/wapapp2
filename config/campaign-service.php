<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Campaign Microservice Enabled Flag
    |--------------------------------------------------------------------------
    | When enabled, Campaign operations will be routed to the standalone
    | campaign-service microservice via CampaignServiceClient.
    */
    'enabled' => (bool) env('CAMPAIGN_SERVICE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Campaign Microservice Base URL
    |--------------------------------------------------------------------------
    */
    'base_url' => rtrim((string) env('CAMPAIGN_SERVICE_BASE_URL', 'http://127.0.0.1:8002/api/v1'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Service-to-Service Shared Secret Token
    |--------------------------------------------------------------------------
    */
    'token' => env('CAMPAIGN_SERVICE_TOKEN', env('SERVICE_AUTH_TOKEN', 'default-campaign-service-secret-token')),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Timeout in Seconds
    |--------------------------------------------------------------------------
    */
    'timeout_seconds' => (int) env('CAMPAIGN_SERVICE_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Graceful Fallback to Monolith Local Implementation
    |--------------------------------------------------------------------------
    | When true, if the campaign-service microservice is unreachable or returns
    | a 5xx error, the adapter falls back to the monolith's local domain service.
    */
    'fallback_to_local' => (bool) env('CAMPAIGN_SERVICE_FALLBACK_TO_LOCAL', true),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Retries
    |--------------------------------------------------------------------------
    */
    'retry_attempts' => (int) env('CAMPAIGN_SERVICE_RETRY_ATTEMPTS', 2),
    'retry_backoff_ms' => (int) env('CAMPAIGN_SERVICE_RETRY_BACKOFF_MS', 100),
];
