<?php

declare(strict_types=1);

return [
    'default' => [
        'host' => sprintf(
            'redis://%s:%d',
            env('REDIS_HOST', '127.0.0.1'),
            (int) env('REDIS_PORT', 6379)
        ),
        'options' => [
            'auth' => env('REDIS_PASSWORD', '') ?: null,
            'db' => (int) env('REDIS_DATABASE', 0),
            'prefix' => env('REDIS_PREFIX', 'pubmatic:'),
            'max_attempts' => 5,
            'retry_seconds' => 5,
        ],
        'pool' => [
            'max_connections' => 5,
            'min_connections' => 1,
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 50,
        ],
    ],
];
