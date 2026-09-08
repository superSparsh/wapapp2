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
        'inbox',
        'billing',
        'integrations',
    ],
];
