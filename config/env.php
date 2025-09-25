<?php
return [
    'db' => [
        'path' => __DIR__ . '/../storage/database.sqlite',
    ],
    'app' => [
        'timezone' => 'UTC',
        'display_timezone' => 'Europe/Tirane',
        'warn_minutes' => 120,
    ],
    'security' => [
        'session_name' => 'fishing_session',
        'csrf_token_key' => '_csrf_token',
        'login_rate_limit' => [
            'window_minutes' => 5,
            'max_attempts' => 5,
            'lock_minutes' => 10,
        ],
    ],
];
