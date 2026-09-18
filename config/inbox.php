<?php

declare(strict_types=1);

return [
    'threads_per_page' => (int) env('INBOX_THREADS_PER_PAGE', 25),

    'messages_per_page' => (int) env('INBOX_MESSAGES_PER_PAGE', 50),

    'max_messages_per_load' => (int) env('INBOX_MAX_MESSAGES_PER_LOAD', 400),

    'default_lookback_days' => (int) env('INBOX_DEFAULT_LOOKBACK_DAYS', 7),

    'allowed_lookback_days' => [1, 3, 7, 90, 180, 365],

    'lookback_labels' => [
        1 => 'Last 1 Day',
        3 => 'Last 3 Days',
        7 => 'Last 7 Days',
        90 => 'Last 3 Months',
        180 => 'Last 6 Months',
        365 => 'Last 1 Year',
    ],

    'poll_interval_ms' => (int) env('INBOX_POLL_INTERVAL_MS', 30000),

    'realtime_enabled' => (bool) env('INBOX_REALTIME_ENABLED', true),

    // Keep close to legacy list/thread polling when websockets briefly drop.
    'realtime_fallback_poll_ms' => (int) env('INBOX_REALTIME_FALLBACK_POLL_MS', 15000),

    'phone_masking_enabled' => (bool) env('INBOX_PHONE_MASKING_ENABLED', false),

    'export_max_conversations' => (int) env('INBOX_EXPORT_MAX_CONVERSATIONS', 500),

    'wallet_min_balance' => (float) env('INBOX_WALLET_MIN_BALANCE', 50),
];
