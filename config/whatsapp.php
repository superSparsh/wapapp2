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
        // Absolute ceiling (largest type). Prefer per-type limits below.
        'max_size_kb' => (int) env('WHATSAPP_MEDIA_MAX_SIZE_KB', 14336),
        'types' => [
            'image' => [
                'max_kb' => (int) env('WHATSAPP_MEDIA_IMAGE_MAX_KB', 5120), // 5 MB
                'extensions' => ['jpeg', 'jpg', 'png', 'webp'],
                'accept' => 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp',
                'hint' => 'JPEG, PNG or WEBP — max 5 MB',
            ],
            'video' => [
                'max_kb' => (int) env('WHATSAPP_MEDIA_VIDEO_MAX_KB', 14336), // 14 MB
                'extensions' => ['mp4', '3gp'],
                'accept' => 'video/mp4,video/3gpp,.mp4,.3gp',
                'hint' => 'MP4 or 3GP — max 14 MB',
            ],
            'document' => [
                'max_kb' => (int) env('WHATSAPP_MEDIA_DOCUMENT_MAX_KB', 14336), // 14 MB
                'extensions' => ['pdf', 'docx', 'xlsx', 'pptx', 'txt'],
                'accept' => '.pdf,.docx,.xlsx,.pptx,.txt,application/pdf,text/plain',
                'hint' => 'PDF, DOCX, XLSX, PPTX or TXT — max 14 MB',
            ],
            'audio' => [
                'max_kb' => (int) env('WHATSAPP_MEDIA_AUDIO_MAX_KB', 14336), // 14 MB
                'extensions' => ['mp3', 'ogg', 'amr', 'aac', 'm4a'],
                'accept' => 'audio/mpeg,audio/ogg,audio/amr,audio/aac,audio/mp4,.mp3,.ogg,.amr,.aac,.m4a',
                'hint' => 'MP3, OGG, AMR, AAC or M4A — max 14 MB',
            ],
        ],
    ],

    'outbound_queue' => env('WHATSAPP_OUTBOUND_QUEUE', 'default'),

    /**
     * alibaba = real CAMS send (fail if not configured)
     * local = mark Sent without provider (tests / explicit local only)
     */
    'outbound_driver' => env('WHATSAPP_OUTBOUND_DRIVER', 'alibaba'),
];
