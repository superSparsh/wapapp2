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
        'webhook_logs' => (int) env('LEGACY_MIGRATE_WEBHOOK_LOG_CHUNK', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily midnight sync (legacy:sync-daily)
    |--------------------------------------------------------------------------
    |
    | Idempotent full re-sync per customer (upsert + restored id_map).
    | New legacy customers are discovered automatically and migrated.
    | Overlap is blocked via the scheduler + an advisory lock.
    |
    */
    'daily_sync' => [
        'enabled' => (bool) env('LEGACY_DAILY_SYNC_ENABLED', true),
        'at' => env('LEGACY_DAILY_SYNC_AT', '00:00'),
        // Soft cap — null/0 = no cap (all customers). Useful for staging.
        'limit' => env('LEGACY_DAILY_SYNC_LIMIT') !== null && env('LEGACY_DAILY_SYNC_LIMIT') !== ''
            ? (int) env('LEGACY_DAILY_SYNC_LIMIT')
            : null,
        // Inbox is the heaviest module; keep on for true parity, skip if nights overrun.
        'skip_inbox' => (bool) env('LEGACY_DAILY_SYNC_SKIP_INBOX', false),
        'skip_billing' => (bool) env('LEGACY_DAILY_SYNC_SKIP_BILLING', false),
        'import_plans' => (bool) env('LEGACY_DAILY_SYNC_IMPORT_PLANS', true),
        'import_settings' => (bool) env('LEGACY_DAILY_SYNC_IMPORT_SETTINGS', true),
        'assign_tenant_plans' => (bool) env('LEGACY_DAILY_SYNC_ASSIGN_PLANS', true),
        // Skip a customer still marked "running" if updated within this many minutes.
        'stale_running_minutes' => (int) env('LEGACY_DAILY_SYNC_STALE_RUNNING', 360),
        'lock_seconds' => (int) env('LEGACY_DAILY_SYNC_LOCK_SECONDS', 82800), // 23h
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
        'list_fields',
        'contacts',
        'segments',
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
        'commerce',
        'webhooks',
    ],
];
