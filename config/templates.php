<?php

declare(strict_types=1);

return [
    'variables_per_page' => (int) env('TEMPLATE_VARIABLES_PER_PAGE', 10),
    'catalog_cache_seconds' => (int) env('TEMPLATE_CATALOG_CACHE_SECONDS', 300),
    'media_disk' => env('TEMPLATE_VARIABLE_MEDIA_DISK', 'local'),
    'media_directory' => 'template-variables',

    // Template builder limits (WhatsApp API constraints)
    'max_buttons' => (int) env('TEMPLATE_MAX_BUTTONS', 10),
    'max_url_buttons' => (int) env('TEMPLATE_MAX_URL_BUTTONS', 2),
    'max_phone_buttons' => (int) env('TEMPLATE_MAX_PHONE_BUTTONS', 1),
    'button_text_limit' => (int) env('TEMPLATE_BUTTON_TEXT_LIMIT', 20),
    'carousel_button_text_limit' => (int) env('TEMPLATE_CAROUSEL_BUTTON_TEXT_LIMIT', 25),
    'button_url_limit' => (int) env('TEMPLATE_BUTTON_URL_LIMIT', 2000),
    'footer_limit' => (int) env('TEMPLATE_FOOTER_LIMIT', 60),
    'header_text_limit' => (int) env('TEMPLATE_HEADER_TEXT_LIMIT', 60),
    'body_limit' => (int) env('TEMPLATE_BODY_LIMIT', 1024),

    // Header media limits (bytes)
    'header_image_max' => (int) env('TEMPLATE_HEADER_IMAGE_MAX', 5242880),   // 5 MB
    'header_video_max' => (int) env('TEMPLATE_HEADER_VIDEO_MAX', 16777216),    // 16 MB
    'header_document_max' => (int) env('TEMPLATE_HEADER_DOC_MAX', 10485760),  // 10 MB
    'header_audio_max' => (int) env('TEMPLATE_HEADER_AUDIO_MAX', 16777216),   // 16 MB

    // Template categories in builder (CAROUSEL may require advance plan)
    'categories' => [
        'MARKETING',
        'UTILITY',
        'AUTHENTICATION',
        'LIMITED_TIME_OFFER',
        'CAROUSEL',
    ],

    // Languages
    'languages' => ['en_GB', 'en_US', 'hi_IN', 'af_ZA', 'ar_AR', 'az_AZ', 'bn_BD', 'bn_IN', 'de_DE', 'es_AR', 'es_ES', 'es_MX', 'fr_FR', 'gu_IN', 'id_ID', 'it_IT', 'ja_JP', 'kn_IN', 'ko_KR', 'ml_IN', 'mr_IN', 'ms_MY', 'nl_NL', 'pa_IN', 'pl_PL', 'pt_BR', 'pt_PT', 'ru_RU', 'sk_SK', 'ta_IN', 'te_IN', 'th_TH', 'tr_TR', 'uk_UA', 'ur_IN', 'ur_PK', 'vi_VN', 'zh_CN', 'zh_TW'],

    // Default template sync TTL (seconds)
    'sync_batch_limit' => (int) env('TEMPLATE_SYNC_BATCH_LIMIT', 50),

    // Carousel templates require advance plan (set false to allow all plans)
    'carousel_requires_advance_plan' => (bool) env('TEMPLATE_CAROUSEL_REQUIRES_ADVANCE_PLAN', true),

    // Carousel card limits
    'carousel_min_cards' => 2,
    'carousel_max_cards' => 10,
];
