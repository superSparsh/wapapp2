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

    /*
    |--------------------------------------------------------------------------
    | Ephemeral Container Instance (campaign worker)
    |--------------------------------------------------------------------------
    |
    | When enabled (and OCI workers enabled), the first campaign that enters
    | "sending" provisions a shared campaign Container Instance. When the last
    | active campaign completes/cancels, the instance is destroyed after a grace
    | period so it does not sit idle.
    |
    | Concurrent campaigns share one instance (refcount). Pause does not destroy.
    |
    | driver:
    | - log  → no OCI API calls (tests / dry-run); stores fake OCIDs in cache
    | - http → signed OCI Container Instances REST API
    */
    'ephemeral' => [
        'enabled' => (bool) env('OCI_EPHEMERAL_CONTAINERS', false),
        'driver' => env('OCI_EPHEMERAL_DRIVER', 'log'),
        'grace_seconds' => (int) env('OCI_EPHEMERAL_GRACE_SECONDS', 120),
        'provisioning_queue' => env('OCI_PROVISIONING_QUEUE', 'provisioning'),

        'region' => env('OCI_REGION', 'ap-mumbai-1'),
        'tenancy_ocid' => env('OCI_TENANCY_OCID', ''),
        'user_ocid' => env('OCI_USER_OCID', ''),
        'fingerprint' => env('OCI_FINGERPRINT', ''),
        // Absolute path to PEM private key, or raw PEM contents
        'private_key' => env('OCI_PRIVATE_KEY', env('OCI_PRIVATE_KEY_PATH', '')),
        'passphrase' => env('OCI_PRIVATE_KEY_PASSPHRASE', ''),

        'compartment_id' => env('OCI_COMPARTMENT_ID', ''),
        'availability_domain' => env('OCI_AVAILABILITY_DOMAIN', ''),
        'subnet_id' => env('OCI_SUBNET_ID', ''),
        'shape' => env('OCI_CI_SHAPE', 'CI.Standard.E4.Flex'),
        // Used when auto_size is disabled, or as floor defaults.
        'ocpus' => (float) env('OCI_CI_OCPUS', 1),
        'memory_in_gbs' => (float) env('OCI_CI_MEMORY_GB', 4),
        'image_url' => env('OCI_CI_IMAGE_URL', ''),
        'display_name_prefix' => env('OCI_CI_DISPLAY_NAME_PREFIX', 'wapapp-campaign-worker'),
        'assign_public_ip' => (bool) env('OCI_CI_ASSIGN_PUBLIC_IP', false),
        'container_restart_policy' => env('OCI_CI_RESTART_POLICY', 'ALWAYS'),

        /*
        | Auto-size the shared CI from the largest active campaign recipient count.
        | Small jobs stay cheap; large jobs get more OCPU/RAM + Horizon campaign processes.
        | Size is chosen once at create time (running CI is not resized mid-flight).
        */
        'auto_size' => [
            'enabled' => (bool) env('OCI_CI_AUTO_SIZE', true),
            'tiers' => [
                // max_recipients null = catch-all (largest)
                [
                    'max_recipients' => (int) env('OCI_CI_TIER_SMALL_MAX', 5000),
                    'ocpus' => (float) env('OCI_CI_TIER_SMALL_OCPUS', 1),
                    'memory_in_gbs' => (float) env('OCI_CI_TIER_SMALL_MEMORY_GB', 4),
                    'campaign_max_processes' => (int) env('OCI_CI_TIER_SMALL_PROCESSES', 2),
                ],
                [
                    'max_recipients' => (int) env('OCI_CI_TIER_MEDIUM_MAX', 50000),
                    'ocpus' => (float) env('OCI_CI_TIER_MEDIUM_OCPUS', 2),
                    'memory_in_gbs' => (float) env('OCI_CI_TIER_MEDIUM_MEMORY_GB', 8),
                    'campaign_max_processes' => (int) env('OCI_CI_TIER_MEDIUM_PROCESSES', 4),
                ],
                [
                    'max_recipients' => null,
                    'ocpus' => (float) env('OCI_CI_TIER_LARGE_OCPUS', 4),
                    'memory_in_gbs' => (float) env('OCI_CI_TIER_LARGE_MEMORY_GB', 16),
                    'campaign_max_processes' => (int) env('OCI_CI_TIER_LARGE_PROCESSES', 8),
                ],
            ],
        ],

        /*
        | Nightly force-destroy schedule time (Asia/Kolkata app timezone unless overridden).
        */
        'nightly_destroy_at' => env('OCI_CI_NIGHTLY_DESTROY_AT', '01:00'),

        /*
        | Redis host reachable FROM the Container Instance (VCN private IP of the
        | app VM). Web can keep REDIS_HOST=127.0.0.1; without this override the CI
        | inherits localhost and never consumes campaign jobs.
        | App VM private IP (prod): 10.0.0.203
        */
        'redis_host' => env('OCI_REDIS_HOST', ''),

        /*
        | Extra env injected into the Horizon container (merged with Redis/DB from
        | the app .env when HTTP driver builds the create payload).
        */
        'container_environment' => [
            'HORIZON_ROLE' => 'oci-heavy',
            'OCI_WORKERS_ENABLED' => 'false',
            'QUEUE_CONNECTION' => 'redis',
        ],
    ],
];
