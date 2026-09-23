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
];
