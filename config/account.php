<?php

declare(strict_types=1);

return [

    'api' => [
        'docs_url' => env('ACCOUNT_API_DOCS_URL', '/profile/api/docs'),
        'base_url' => env('ACCOUNT_API_BASE_URL', env('APP_URL', 'http://localhost').'/api/v1'),
    ],

    'timezones' => [
        'Asia/Kolkata' => '(GMT+05:30) Asia/Kolkata',
        'Asia/Dubai' => '(GMT+04:00) Asia/Dubai',
        'Asia/Singapore' => '(GMT+08:00) Asia/Singapore',
        'Europe/London' => '(GMT+00:00) Europe/London',
        'America/New_York' => '(GMT-05:00) America/New_York',
        'UTC' => '(GMT+00:00) UTC',
    ],

    'countries' => [
        'IN' => 'India',
        'AE' => 'United Arab Emirates',
        'SG' => 'Singapore',
        'GB' => 'United Kingdom',
        'US' => 'United States',
    ],

    'locales' => [
        'en' => 'English',
        'hi' => 'Hindi',
        'ar' => 'Arabic',
    ],

    'notification_types' => [
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
    ],

    'avatar' => [
        'disk' => 'public',
        'directory' => 'avatars',
        'max_kb' => 2048,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

];
