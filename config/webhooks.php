<?php

declare(strict_types=1);

return [
    'alibaba' => [
        'legacy_message_path' => 'api/v1/message-uplink/alibaba',
        'legacy_status_path' => 'api/v1/status-uplink/alibaba',
    ],

    'queue' => env('INBOUND_WEBHOOK_QUEUE', 'default'),

    'max_retries' => (int) env('INBOUND_WEBHOOK_MAX_RETRIES', 3),
];
