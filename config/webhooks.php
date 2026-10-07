<?php

declare(strict_types=1);

return [
    'alibaba' => [
        'legacy_message_path' => 'api/v1/message-uplink/alibaba',
        'legacy_status_path' => 'api/v1/status-uplink/alibaba',
    ],

    /*
    | Default queue for inbound webhook job retries (non-status).
    | Delivery/read status uses oci-workers.queues.status when routed via OciWorkload.
    */
    'queue' => env('INBOUND_WEBHOOK_QUEUE', 'messages'),

    'max_retries' => (int) env('INBOUND_WEBHOOK_MAX_RETRIES', 3),

    /*
    | Automated inbound_webhook_events hygiene (webhooks:maintain-inbound).
    | - Replay stuck status rows inside the keep window onto the status queue
    | - Delete every status older than keep_days (nightly full run)
    */
    'inbound_maintenance' => [
        'enabled' => (bool) env('INBOUND_WEBHOOK_MAINTENANCE_ENABLED', true),
        /*
         * How long to keep rows for Alibaba retry dedup + same-day debug.
         * Real-world retries usually land within minutes–a few hours; 12h is a solid default.
         */
        'keep_hours' => (int) env('INBOUND_WEBHOOK_KEEP_HOURS', 12),
        'replay_batch' => (int) env('INBOUND_WEBHOOK_REPLAY_BATCH', 500),
        'replay_max' => (int) env('INBOUND_WEBHOOK_REPLAY_MAX', 10000),
        'replay_max_batches' => (int) env('INBOUND_WEBHOOK_REPLAY_MAX_BATCHES', 4),
        'replay_grace_minutes' => (int) env('INBOUND_WEBHOOK_REPLAY_GRACE_MINUTES', 10),
        'prune_batch' => (int) env('INBOUND_WEBHOOK_PRUNE_BATCH', 5000),
        'prune_max_rounds' => (int) env('INBOUND_WEBHOOK_PRUNE_MAX_ROUNDS', 50),
        // Full replay + prune older than keep window
        'nightly_at' => env('INBOUND_WEBHOOK_MAINTENANCE_AT', '02:30'),
    ],
];
