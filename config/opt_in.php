<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Opt-in template V3 (Utility wording) rollout
    |--------------------------------------------------------------------------
    |
    | Legacy parity: original Marketing template `opt_in_message` (Yes/No/STOP)
    | is the only one used for sends. V3 (`opt_in_message_v3`) stays unused
    | unless explicitly enabled — legacy hard-disabled that rollout.
    |
    */

    'v2_global' => (bool) env('OPT_IN_V2_GLOBAL', false),

    'v2_customer_ids' => array_values(array_filter(array_map(
        static fn ($id) => (int) trim((string) $id),
        explode(',', (string) env('OPT_IN_V2_CUSTOMER_IDS', ''))
    ), static fn (int $id) => $id > 0)),

    /*
    |--------------------------------------------------------------------------
    | STOP / START keyword handling (inbound WhatsApp)
    |--------------------------------------------------------------------------
    |
    | Matching is case-insensitive. Exact match after normalize (plain text or
    | interactive button/list title).
    |
    */

    'stop_keywords' => [
        'stop',
        'stop promotions',
    ],

    'start_keywords' => [
        'start',
    ],

    'stop_confirmation' => 'Thank you. You have been unsubscribed and will no longer receive promotional messages from us. To start receiving messages again, reply START.',

    'start_confirmation' => 'Welcome back! You have been re-subscribed and can receive messages again.',

];
