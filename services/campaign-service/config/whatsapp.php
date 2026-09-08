<?php

declare(strict_types=1);

return [
    'alibaba' => [
        'access_key_id' => env('ALIBABA_ACCESS_KEY_ID'),
        'access_key_secret' => env('ALIBABA_ACCESS_KEY_SECRET'),
        'endpoint' => env('ALIBABA_ENDPOINT', 'cams.ap-southeast-1.aliyuncs.com'),
        'default_language' => env('WHATSAPP_DEFAULT_LANGUAGE', 'en_GB'),
    ],
];
