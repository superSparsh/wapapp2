<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Chatbot Configuration
    |--------------------------------------------------------------------------
    */

    // Conversation state TTL in minutes before auto-expiry (legacy waits ~5+ min; keep sessions usable)
    'state_ttl_minutes' => (int) env('CHATBOT_STATE_TTL', 60),

    // Flow data cache TTL in seconds (2 hours)
    'flow_cache_ttl_seconds' => (int) env('CHATBOT_CACHE_TTL', 7200),

    // Maximum nodes allowed per flow
    'max_nodes_per_flow' => 100,

    // Maximum flows per tenant
    'max_flows_per_tenant' => 50,

    // Queue name for delayed node processing
    'delay_queue' => 'chatbot',

    // Debug logging for chatbot engine
    'debug' => (bool) env('DEBUG_CHATBOT', false),

    // Minimum wallet balance required for chatbot to fire
    'wallet_min_balance' => (float) env('CHATBOT_WALLET_MIN', 50.0),

    /*
    | Demo flows keep replying during client demos even if wallet is low
    | or a node fails (gateway, missing template, bad interactive payload).
    | Comma-separated exact names. Matching is case-insensitive.
    */
    'demo_flow_names' => array_values(array_filter(array_map(
        static fn (string $name): string => trim($name),
        explode(',', (string) env(
            'CHATBOT_DEMO_FLOW_NAMES',
            'tittu chatbot using interactive messages',
        )),
    ))),

    'demo_fallback_message' => (string) env(
        'CHATBOT_DEMO_FALLBACK',
        'Thanks for your message. Reply with a menu option to continue.',
    ),

    // "start" command to reset conversation
    'start_command' => 'start',

    // Pagination
    'per_page' => 10,

    // Drip Marketing
    'drip' => [
        'per_page' => 10,
        'default_timezone' => 'Asia/Kolkata',
        'max_campaigns_per_tenant' => 50,
    ],
];
