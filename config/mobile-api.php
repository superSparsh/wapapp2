<?php

declare(strict_types=1);

return [
    'access_ttl_seconds' => (int) env('MOBILE_JWT_ACCESS_TTL', 3600),
    'refresh_ttl_seconds' => (int) env('MOBILE_JWT_REFRESH_TTL', 2_592_000), // 30 days
    'issuer' => env('MOBILE_JWT_ISSUER', env('APP_URL', 'http://localhost')),
];
