<?php
return [
    'app_name' => 'Simple Checkers',
    'default_language' => 'en',
    'supported_languages' => ['en', 'es'],
    'data_dir' => __DIR__ . '/data',
    'database_path' => __DIR__ . '/data/checkers.sqlite',
    'error_display' => getenv('APP_DEBUG') === '1',
    'reminder' => [
        'enabled' => false,
        'delay_seconds' => 86400,
        'from_email' => 'noreply@example.com',
    ],
    'prune' => [
        'enabled' => false,
        'inactive_days' => 60,
    ],
    'security' => [
        'csp_enabled' => true,
        'cookie_secure' => false,
    ],
];
