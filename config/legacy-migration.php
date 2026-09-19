<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy Database Connection
    |--------------------------------------------------------------------------
    |
    | Reuses the same LEGACY_DB_* env vars as help-center:import-legacy.
    |
    */
    'connection' => env('LEGACY_DB_CONNECTION', 'legacy'),

    /*
    |--------------------------------------------------------------------------
    | Legacy app filesystem / public URL
    |--------------------------------------------------------------------------
    |
    | Used to copy template header media and resolve relative /upload/... paths
    | when importing templates from the old WapApp install.
    |
    */
    'app_path' => env('LEGACY_APP_PATH', '/Applications/MAMP/htdocs/wapdev.tittu.in'),
    'app_url' => env('LEGACY_APP_URL', env('LEGACY_URL', '')),

    /*
    |--------------------------------------------------------------------------
    | Default pilot selection
    |--------------------------------------------------------------------------
    |
    | When --pilot is passed, pick a customer with lines + email, subscribers
    | under this cap, and the richest feature mix (templates/campaigns/etc).
    |
    */
    'pilot' => [
        'max_subscribers' => (int) env('LEGACY_MIGRATE_PILOT_MAX_SUBS', 5000),
        'require_lines' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunk sizes
    |--------------------------------------------------------------------------
    */
    'chunks' => [
        'contacts' => (int) env('LEGACY_MIGRATE_CONTACT_CHUNK', 500),
        'messages' => (int) env('LEGACY_MIGRATE_MESSAGE_CHUNK', 300),
        'campaign_recipients' => (int) env('LEGACY_MIGRATE_RECIPIENT_CHUNK', 500),
        'inbox_threads' => (int) env('LEGACY_MIGRATE_INBOX_CHUNK', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules (run order matters)
    |--------------------------------------------------------------------------
    |
    | FAQs and tutorials are central (not per-customer). Import with:
    |   php artisan help-center:import-legacy --force
    |
    */
    'modules' => [
        'owner',
        'lines',
        'lists',
        'contacts',
        'templates',
        'interactive_messages',
        'variables',
        'forms',
        'trigger_templates',
        'team',
        'campaigns',
        'chatbots',
        'drips',
        'whatsapp_flows',
        'ai',
        'ai_settings',
        'inbox',
        'billing',
        'integrations',
    ],
];
