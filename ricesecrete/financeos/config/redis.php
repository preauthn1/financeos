<?php

declare(strict_types=1);

return [
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('REDIS_PORT', 6379),
        'auth' => env('REDIS_PASSWORD', ''),
        'database' => (int) env('REDIS_DATABASE', 0),
        'prefix' => env('REDIS_PREFIX', 'pubmatic:'),
        'timeout' => 2,
    ],
];
