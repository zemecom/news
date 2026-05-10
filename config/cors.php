<?php

declare(strict_types=1);

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_WEB_URL', 'http://app.localhost:3000'),
        env('FRONTEND_ADMIN_URL', 'http://admin.localhost:3001'),
        env('APP_URL', 'http://api.localhost:8080'),
    ])),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
