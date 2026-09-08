<?php

declare(strict_types=1);

return [
    'driver' => env('LOGIN_OTP_DRIVER', 'log'),

    'ttl_seconds' => (int) env('LOGIN_OTP_TTL', 600),

    'resend_cooldown_seconds' => (int) env('LOGIN_OTP_RESEND_COOLDOWN', 60),

    'max_attempts' => (int) env('LOGIN_OTP_MAX_VERIFY_ATTEMPTS', 5),

    'whatsapp' => [
        'from' => env('LOGIN_OTP_WHATSAPP_FROM'),
        'template_code' => env('LOGIN_OTP_WHATSAPP_TEMPLATE_CODE'),
        'cust_space_id' => env('LOGIN_OTP_WHATSAPP_CUST_SPACE_ID'),
        'template_param' => env('LOGIN_OTP_WHATSAPP_TEMPLATE_PARAM', 'verificationCode'),
    ],

    'alibaba' => [
        'access_key_id' => env('ALIBABA_ACCESS_KEY_ID'),
        'access_key_secret' => env('ALIBABA_ACCESS_KEY_SECRET'),
        'endpoint' => env('ALIBABA_ENDPOINT', 'cams.ap-southeast-1.aliyuncs.com'),
    ],
];
