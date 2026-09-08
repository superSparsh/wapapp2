<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Chatbot Configuration
    |--------------------------------------------------------------------------
    */

    // Conversation state TTL in minutes before auto-expiry
    'state_ttl_minutes' => (int) env('CHATBOT_STATE_TTL', 2),

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
