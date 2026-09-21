<?php

declare(strict_types=1);

/**
 * Allowlisted server ops for Admin → Queues.
 * Only these keys can be executed — no free-form shell.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Shell / sudo
    |--------------------------------------------------------------------------
    |
    | PHP-FPM user needs passwordless sudo (-n) for supervisor/apache commands.
    | Example sudoers:
    |   www-data ALL=(root) NOPASSWD: /usr/bin/supervisorctl, /bin/systemctl reload apache2, /usr/sbin/apache2ctl
    |
    */

    'use_sudo' => (bool) env('ADMIN_OPS_SUDO', true),
    'sudo_bin' => env('ADMIN_OPS_SUDO_BIN', '/usr/bin/sudo'),
    'timeout' => (int) env('ADMIN_OPS_TIMEOUT', 45),

    'supervisorctl_bin' => env('ADMIN_OPS_SUPERVISORCTL', '/usr/bin/supervisorctl'),
    'supervisor_program' => env('ADMIN_OPS_SUPERVISOR_PROGRAM', 'horizon'),

    'apache_service' => env('ADMIN_OPS_APACHE_SERVICE', 'apache2'), // debian: apache2, rhel: httpd
    'apachectl_bin' => env('ADMIN_OPS_APACHECTL', '/usr/sbin/apache2ctl'),
    'systemctl_bin' => env('ADMIN_OPS_SYSTEMCTL', '/bin/systemctl'),
    'redis_cli_bin' => env('ADMIN_OPS_REDIS_CLI', '/usr/bin/redis-cli'),

    /*
    |--------------------------------------------------------------------------
    | Commands (allowlist)
    |--------------------------------------------------------------------------
    |
    | type: artisan | shell | redis (Laravel Redis facade)
    | danger: requires stronger confirm in UI
    |
    */

    'commands' => [

        // ── Queue / Horizon ──────────────────────────────────────────
        'queue_restart' => [
            'group' => 'Queue / Horizon',
            'label' => 'Restart queue workers',
            'description' => 'queue:restart — workers finish current job then reload code.',
            'type' => 'artisan',
            'command' => 'queue:restart',
            'danger' => false,
        ],
        'horizon_status' => [
            'group' => 'Queue / Horizon',
            'label' => 'Horizon status',
            'description' => 'horizon:status',
            'type' => 'artisan',
            'command' => 'horizon:status',
            'danger' => false,
        ],
        'horizon_terminate' => [
            'group' => 'Queue / Horizon',
            'label' => 'Terminate Horizon',
            'description' => 'horizon:terminate — Supervisor should restart Horizon.',
            'type' => 'artisan',
            'command' => 'horizon:terminate',
            'danger' => true,
        ],
        'horizon_pause' => [
            'group' => 'Queue / Horizon',
            'label' => 'Pause Horizon',
            'description' => 'horizon:pause — stop processing new jobs.',
            'type' => 'artisan',
            'command' => 'horizon:pause',
            'danger' => true,
        ],
        'horizon_continue' => [
            'group' => 'Queue / Horizon',
            'label' => 'Continue Horizon',
            'description' => 'horizon:continue — resume after pause.',
            'type' => 'artisan',
            'command' => 'horizon:continue',
            'danger' => false,
        ],
        'horizon_snapshot' => [
            'group' => 'Queue / Horizon',
            'label' => 'Horizon metrics snapshot',
            'description' => 'horizon:snapshot',
            'type' => 'artisan',
            'command' => 'horizon:snapshot',
            'danger' => false,
        ],
        'horizon_clear' => [
            'group' => 'Queue / Horizon',
            'label' => 'Clear Horizon jobs',
            'description' => 'horizon:clear — purge pending Horizon jobs (careful).',
            'type' => 'artisan',
            'command' => 'horizon:clear',
            'danger' => true,
        ],

        // ── Laravel cache / config ───────────────────────────────────
        'optimize_clear' => [
            'group' => 'Laravel cache',
            'label' => 'Optimize clear (all)',
            'description' => 'optimize:clear — config, route, view, event, cache.',
            'type' => 'artisan',
            'command' => 'optimize:clear',
            'danger' => false,
        ],
        'config_clear' => [
            'group' => 'Laravel cache',
            'label' => 'Config clear',
            'description' => 'config:clear',
            'type' => 'artisan',
            'command' => 'config:clear',
            'danger' => false,
        ],
        'config_cache' => [
            'group' => 'Laravel cache',
            'label' => 'Config cache',
            'description' => 'config:cache — rebuild config cache (production).',
            'type' => 'artisan',
            'command' => 'config:cache',
            'danger' => false,
        ],
        'cache_clear' => [
            'group' => 'Laravel cache',
            'label' => 'Application cache clear',
            'description' => 'cache:clear',
            'type' => 'artisan',
            'command' => 'cache:clear',
            'danger' => false,
        ],
        'route_clear' => [
            'group' => 'Laravel cache',
            'label' => 'Route clear',
            'description' => 'route:clear',
            'type' => 'artisan',
            'command' => 'route:clear',
            'danger' => false,
        ],
        'route_cache' => [
            'group' => 'Laravel cache',
            'label' => 'Route cache',
            'description' => 'route:cache',
            'type' => 'artisan',
            'command' => 'route:cache',
            'danger' => false,
        ],
        'view_clear' => [
            'group' => 'Laravel cache',
            'label' => 'View clear',
            'description' => 'view:clear',
            'type' => 'artisan',
            'command' => 'view:clear',
            'danger' => false,
        ],
        'event_clear' => [
            'group' => 'Laravel cache',
            'label' => 'Event clear',
            'description' => 'event:clear',
            'type' => 'artisan',
            'command' => 'event:clear',
            'danger' => false,
        ],
        'queue_failed_flush' => [
            'group' => 'Laravel cache',
            'label' => 'Flush failed jobs table',
            'description' => 'queue:flush — delete all failed_jobs rows.',
            'type' => 'artisan',
            'command' => 'queue:flush',
            'danger' => true,
        ],

        // ── Redis ────────────────────────────────────────────────────
        'redis_ping' => [
            'group' => 'Redis',
            'label' => 'Redis PING',
            'description' => 'Laravel Redis connection ping.',
            'type' => 'redis',
            'command' => 'ping',
            'danger' => false,
        ],
        'redis_info' => [
            'group' => 'Redis',
            'label' => 'Redis INFO (summary)',
            'description' => 'redis-cli INFO Server / Memory / Clients (read-only).',
            'type' => 'shell',
            'argv' => ['{redis_cli}', 'INFO'],
            'danger' => false,
        ],
        'redis_cli_ping' => [
            'group' => 'Redis',
            'label' => 'redis-cli PING',
            'description' => 'Shell redis-cli ping.',
            'type' => 'shell',
            'argv' => ['{redis_cli}', 'PING'],
            'danger' => false,
        ],

        // ── Supervisor ───────────────────────────────────────────────
        'supervisor_status' => [
            'group' => 'Supervisor',
            'label' => 'Supervisor status',
            'description' => 'supervisorctl status',
            'type' => 'shell',
            'argv' => ['{supervisorctl}', 'status'],
            'sudo' => true,
            'danger' => false,
        ],
        'supervisor_reread' => [
            'group' => 'Supervisor',
            'label' => 'Supervisor reread',
            'description' => 'supervisorctl reread',
            'type' => 'shell',
            'argv' => ['{supervisorctl}', 'reread'],
            'sudo' => true,
            'danger' => false,
        ],
        'supervisor_update' => [
            'group' => 'Supervisor',
            'label' => 'Supervisor update',
            'description' => 'supervisorctl update',
            'type' => 'shell',
            'argv' => ['{supervisorctl}', 'update'],
            'sudo' => true,
            'danger' => true,
        ],
        'supervisor_restart_horizon' => [
            'group' => 'Supervisor',
            'label' => 'Restart Horizon program',
            'description' => 'supervisorctl restart {program} (default: horizon).',
            'type' => 'shell',
            'argv' => ['{supervisorctl}', 'restart', '{supervisor_program}'],
            'sudo' => true,
            'danger' => true,
        ],
        'supervisor_restart_all' => [
            'group' => 'Supervisor',
            'label' => 'Restart all Supervisor programs',
            'description' => 'supervisorctl restart all',
            'type' => 'shell',
            'argv' => ['{supervisorctl}', 'restart', 'all'],
            'sudo' => true,
            'danger' => true,
        ],
        'supervisor_stop_horizon' => [
            'group' => 'Supervisor',
            'label' => 'Stop Horizon program',
            'description' => 'supervisorctl stop {program}',
            'type' => 'shell',
            'argv' => ['{supervisorctl}', 'stop', '{supervisor_program}'],
            'sudo' => true,
            'danger' => true,
        ],
        'supervisor_start_horizon' => [
            'group' => 'Supervisor',
            'label' => 'Start Horizon program',
            'description' => 'supervisorctl start {program}',
            'type' => 'shell',
            'argv' => ['{supervisorctl}', 'start', '{supervisor_program}'],
            'sudo' => true,
            'danger' => false,
        ],

        // ── Apache ───────────────────────────────────────────────────
        'apache_status' => [
            'group' => 'Apache',
            'label' => 'Apache service status',
            'description' => 'systemctl status {apache_service} --no-pager',
            'type' => 'shell',
            'argv' => ['{systemctl}', 'status', '{apache_service}', '--no-pager'],
            'sudo' => true,
            'danger' => false,
        ],
        'apache_configtest' => [
            'group' => 'Apache',
            'label' => 'Apache configtest',
            'description' => 'apache2ctl / httpd -t',
            'type' => 'shell',
            'argv' => ['{apachectl}', '-t'],
            'sudo' => true,
            'danger' => false,
        ],
        'apache_reload' => [
            'group' => 'Apache',
            'label' => 'Reload Apache',
            'description' => 'systemctl reload {apache_service} — graceful reload.',
            'type' => 'shell',
            'argv' => ['{systemctl}', 'reload', '{apache_service}'],
            'sudo' => true,
            'danger' => true,
        ],
        'apache_restart' => [
            'group' => 'Apache',
            'label' => 'Restart Apache',
            'description' => 'systemctl restart {apache_service} — brief downtime.',
            'type' => 'shell',
            'argv' => ['{systemctl}', 'restart', '{apache_service}'],
            'sudo' => true,
            'danger' => true,
        ],
    ],
];
