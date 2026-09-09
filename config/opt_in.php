<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Opt-in template V3 (Utility wording) rollout
    |--------------------------------------------------------------------------
    |
    | Legacy parity: OPT_IN_V2_GLOBAL / OPT_IN_V2_CUSTOMER_IDS control whether
    | tenants use opt_in_message_v3 (Utility) vs legacy opt_in_message (Marketing).
    |
    */

    'v2_global' => (bool) env('OPT_IN_V2_GLOBAL', true),

    'v2_customer_ids' => array_values(array_filter(array_map(
        static fn ($id) => (int) trim((string) $id),
        explode(',', (string) env('OPT_IN_V2_CUSTOMER_IDS', ''))
    ), static fn (int $id) => $id > 0)),

];
