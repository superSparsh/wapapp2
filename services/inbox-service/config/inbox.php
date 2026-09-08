<?php

declare(strict_types=1);

return [
    'threads_per_page' => (int) env('INBOX_THREADS_PER_PAGE', 25),
    'messages_per_page' => (int) env('INBOX_MESSAGES_PER_PAGE', 50),
    'max_messages_per_load' => (int) env('INBOX_MAX_MESSAGES_PER_LOAD', 400),
    'default_lookback_days' => (int) env('INBOX_DEFAULT_LOOKBACK_DAYS', 7),
    'allowed_lookback_days' => [1, 3, 7, 30, 90],
    'export_max_conversations' => (int) env('INBOX_EXPORT_MAX_CONVERSATIONS', 500),
];
