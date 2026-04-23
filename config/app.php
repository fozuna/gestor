<?php
declare(strict_types=1);

return [
    'name' => (string)(function_exists('runtime_env') ? runtime_env('APP_NAME', 'TRAXTER CRM') : (getenv('APP_NAME') ?: 'TRAXTER CRM')),
    'env' => (string)(function_exists('runtime_env') ? runtime_env('APP_ENV', 'production') : (getenv('APP_ENV') ?: 'production')),
    'url' => (string)(function_exists('runtime_env') ? runtime_env('APP_URL', 'http://localhost') : (getenv('APP_URL') ?: 'http://localhost')),
    'key' => (string)(function_exists('runtime_env') ? runtime_env('APP_KEY', '') : (getenv('APP_KEY') ?: '')),
    'timezone' => (string)(function_exists('runtime_env') ? runtime_env('APP_TIMEZONE', 'America/Campo_Grande') : (getenv('APP_TIMEZONE') ?: 'America/Campo_Grande')),
];

