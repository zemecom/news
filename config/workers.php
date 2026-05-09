<?php

declare(strict_types=1);

return [
    'supervisor' => [
        'driver' => env('WORKER_SUPERVISOR_DRIVER', 'null'),
        'url' => env('WORKER_SUPERVISOR_URL', 'http://worker-control:8081'),
        'token' => env('WORKER_SUPERVISOR_TOKEN', 'local-worker-supervisor-token'),
        'log_tail_lines' => (int) env('WORKER_LOG_TAIL_LINES', 50),
    ],

    'heartbeat_ttl_seconds' => (int) env('WORKER_HEARTBEAT_TTL_SECONDS', 120),
    'run_until_empty_max_jobs' => (int) env('WORKER_RUN_UNTIL_EMPTY_MAX_JOBS', 25),
    'telemetry_store' => env('WORKER_TELEMETRY_STORE', env('CACHE_STORE', 'redis')),

    'runtimes' => [
        'worker' => [
            'service' => 'worker',
            'queues' => [
                'crawler_tasks',
                'intelligence_tasks',
                'media_tasks',
            ],
        ],
    ],

    'operator_commands' => [
        'start_all' => 'docker compose --profile queue up -d worker',
        'restart_runtime_template' => 'docker compose restart worker',
        'scale_runtime_hint_template' => 'docker compose --profile queue up -d --scale worker=%d worker',
    ],
];
