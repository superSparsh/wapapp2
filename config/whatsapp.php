<?php

declare(strict_types=1);

return [
    'alibaba' => [
        'access_key_id' => env('ALIBABA_ACCESS_KEY_ID'),
        'access_key_secret' => env('ALIBABA_ACCESS_KEY_SECRET'),
        'endpoint' => env('ALIBABA_ENDPOINT', 'cams.ap-southeast-1.aliyuncs.com'),
        'default_language' => env('WHATSAPP_DEFAULT_LANGUAGE', 'en_GB'),
    ],

    'service_window_hours' => (int) env('WHATSAPP_SERVICE_WINDOW_HOURS', 24),

    'media' => [
        'disk' => env('WHATSAPP_MEDIA_DISK', 'public'),
        'directory' => env('WHATSAPP_MEDIA_DIRECTORY', 'inbox/outbound'),
        'max_size_kb' => (int) env('WHATSAPP_MEDIA_MAX_SIZE_KB', 16384),
    ],

    'outbound_queue' => env('WHATSAPP_OUTBOUND_QUEUE', 'default'),

    /**
     * alibaba = real CAMS send (fail if not configured)
     * local = mark Sent without provider (tests / explicit local only)
     */
    'outbound_driver' => env('WHATSAPP_OUTBOUND_DRIVER', 'alibaba'),
];
