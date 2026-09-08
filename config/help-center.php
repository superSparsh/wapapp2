<?php

declare(strict_types=1);

return [
    'cache' => [
        'ttl' => 3600,
        'faqs' => 'help-center.faqs.active',
        'tutorials' => 'help-center.tutorials.active',
    ],
    'video_path' => public_path('assets/videos/tutorials'),
    'legacy' => [
        'connection' => env('LEGACY_DB_CONNECTION', 'legacy'),
        'app_path' => env('LEGACY_APP_PATH', '/Applications/MAMP/htdocs/wapdev.tittu.in'),
        'faq_table' => 'faq',
        'tutorial_videos_table' => 'tutorial_videos',
        'video_source_path' => env(
            'LEGACY_TUTORIAL_VIDEO_PATH',
            env('LEGACY_APP_PATH', '/Applications/MAMP/htdocs/wapdev.tittu.in').'/public/assets/videos/tutorials',
        ),
    ],
];
