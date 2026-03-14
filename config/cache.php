<?php

declare(strict_types=1);

return [
    'driver' => getenv('CACHE_DRIVER') ?: 'array',
    'file_path' => getenv('CACHE_PATH') ?: sys_get_temp_dir() . '/ez-cache',
    'redis' => [
        'host' => getenv('CACHE_REDIS_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('CACHE_REDIS_PORT') ?: 6379),
        'database' => (int) (getenv('CACHE_REDIS_DB') ?: 0),
    ],
];
