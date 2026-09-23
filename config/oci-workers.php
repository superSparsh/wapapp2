<?php

declare(strict_types=1);

/**
 * OCI heavy-worker routing (campaign / status / large imports).
 *
 * When disabled, behaviour matches classic single-host Horizon (all queues local).
 */
return [

    /*
    | Master switch. Keep false until OCI Horizon (HORIZON_ROLE=oci-heavy) is running
    | and sharing the same Redis as the web app.
    */
    'enabled' => (bool) env('OCI_WORKERS_ENABLED', false),

    /*
    | Horizon role on this host:
    | - all       → process every supervisor (default / local / emergency fallback)
    | - web       → light queues only (no campaign/status/import)
    | - oci-heavy → campaign + status + import only
    */
    'horizon_role' => env('HORIZON_ROLE', 'all'),

    'queues' => [
        'campaign' => env('CAMPAIGN_QUEUE', 'campaign'),
        'status' => env('OCI_STATUS_QUEUE', env('INBOUND_STATUS_QUEUE', 'status')),
        'import' => env('OCI_IMPORT_QUEUE', 'import'),
        'messages' => env('INBOUND_MESSAGE_QUEUE', 'messages'),
    ],

    /*
    | Contact CSV row count at or above this value is dispatched to the import queue
    | (OCI when enabled). Smaller files stay on the default/web workers.
    */
    'import_row_threshold' => (int) env('OCI_IMPORT_ROW_THRESHOLD', 30000),

    /*
    | When OCI is enabled, skip sync processing for delivery-status webhooks and
    | enqueue to the status queue only (keeps web request fast under burst).
    | Message (chat) webhooks still prefer sync for chatbot latency.
    */
    'status_queue_only' => (bool) env('OCI_STATUS_QUEUE_ONLY', true),
];
