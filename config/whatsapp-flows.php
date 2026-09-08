<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Flows Configuration
    |--------------------------------------------------------------------------
    */

    // Pagination: flows per page in index listing
    'per_page' => (int) env('WHATSAPP_FLOWS_PER_PAGE', 10),

    // Maximum screens allowed per flow (legacy parity: 8)
    'max_screens_per_flow' => 8,

    // Maximum fields allowed per screen
    'max_fields_per_screen' => 50,

    // Submission data retention in days (submissions older than this are purged)
    'submission_retention_days' => (int) env('WHATSAPP_FLOWS_RETENTION_DAYS', 90),

    // Maximum flows per tenant
    'max_flows_per_tenant' => 100,

    // Queue name for submission processing
    'queue' => 'whatsapp-flows',
];
