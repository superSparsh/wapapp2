<?php

declare(strict_types=1);

return [
    'service_window_hours' => (int) env('WHATSAPP_SERVICE_WINDOW_HOURS', 24),
    'outbound_queue' => env('WHATSAPP_OUTBOUND_QUEUE', 'default'),
    'media' => [
        'disk' => env('WHATSAPP_MEDIA_DISK', 'public'),
        'directory' => env('WHATSAPP_MEDIA_DIRECTORY', 'inbox/outbound'),
    ],
    'alibaba' => [
        'access_key_id' => env('ALIBABA_ACCESS_KEY_ID'),
        'access_key_secret' => env('ALIBABA_ACCESS_KEY_SECRET'),
        'endpoint' => env('ALIBABA_ENDPOINT', 'cams.ap-southeast-1.aliyuncs.com'),
        'default_language' => env('ALIBABA_DEFAULT_LANGUAGE', 'en_GB'),
    ],
];
