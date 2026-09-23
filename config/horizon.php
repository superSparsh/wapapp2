<?php

use App\Support\HorizonRole;
use Illuminate\Support\Str;

$horizon = [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    */

    'name' => env('HORIZON_NAME'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => ['web'],

    'waits' => [
        'redis:default' => 60,
        'redis:critical' => 30,
        'redis:messages' => 60,
        'redis:status' => 90,
        'redis:campaign' => 120,
        'redis:import' => 300,
        'redis:chatbot' => 60,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Supervisors are filtered by HORIZON_ROLE (web | oci-heavy | all) via
    | HorizonRole::filterConfig at the bottom of this file.
    |
    */

    'defaults' => [
        'critical' => [
            'connection' => 'redis',
            'queue' => ['critical'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 5,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 5,
            'timeout' => 30,
            'nice' => 0,
        ],
        'messages' => [
            'connection' => 'redis',
            'queue' => ['messages'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 4,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
        'status' => [
            'connection' => 'redis',
            'queue' => ['status'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 3,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 30,
            'nice' => 0,
        ],
        'campaign' => [
            'connection' => 'redis',
            'queue' => ['campaign'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 3,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
        'import' => [
            'connection' => 'redis',
            'queue' => ['import'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'memory' => 512,
            'tries' => 1,
            'timeout' => 7200,
            'nice' => 0,
        ],
        'automation' => [
            'connection' => 'redis',
            'queue' => ['automation'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 2,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
        'default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 2,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
        'low' => [
            'connection' => 'redis',
            'queue' => ['low'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'memory' => 128,
            'tries' => 2,
            'timeout' => 300,
            'nice' => 0,
        ],
        'ai' => [
            'connection' => 'redis',
            'queue' => ['ai'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 2,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
        'chatbot' => [
            'connection' => 'redis',
            'queue' => ['chatbot'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 2,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
        'provisioning' => [
            'connection' => 'redis',
            'queue' => ['provisioning'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'memory' => 128,
            'tries' => 2,
            'timeout' => 300,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'critical' => [
                'maxProcesses' => 5,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
            ],
            'messages' => [
                'maxProcesses' => 4,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
            ],
            'status' => [
                'maxProcesses' => 6,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
            ],
            'campaign' => [
                'maxProcesses' => 5,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 5,
            ],
            'import' => [
                'maxProcesses' => 2,
            ],
            'automation' => [
                'maxProcesses' => 2,
            ],
            'default' => [
                'maxProcesses' => 2,
            ],
            'low' => [
                'maxProcesses' => 1,
            ],
            'ai' => [
                'maxProcesses' => 2,
            ],
            'chatbot' => [
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'provisioning' => [
                'maxProcesses' => 1,
            ],
        ],

        'local' => [
            'critical' => ['maxProcesses' => 2],
            'messages' => ['maxProcesses' => 2],
            'status' => ['maxProcesses' => 1],
            'campaign' => ['maxProcesses' => 1],
            'import' => ['maxProcesses' => 1],
            'automation' => ['maxProcesses' => 1],
            'default' => ['maxProcesses' => 1],
            'low' => ['maxProcesses' => 1],
            'ai' => ['maxProcesses' => 1],
            'chatbot' => ['maxProcesses' => 1],
            'provisioning' => ['maxProcesses' => 1],
        ],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];

return HorizonRole::filterConfig($horizon, env('HORIZON_ROLE', 'all'));
