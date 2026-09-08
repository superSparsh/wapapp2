<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Inbox Logging Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines how logging should be handled in the Inbox service.
    |
    */

    'enabled' => env('INBOX_LOGGING_ENABLED', true),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'slack'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/inbox.log'),
            'level' => env('INBOX_LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('INBOX_SLACK_WEBHOOK_URL'),
            'username' => env('INBOX_SLACK_USERNAME', 'Inbox Service'),
            'emoji' => env('INBOX_SLACK_EMOJI', ':boom:'),
            'level' => env('INBOX_SLACK_LEVEL', 'critical'),
        ],
    ],
];
