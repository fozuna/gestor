<?php
declare(strict_types=1);

$env = static fn(string $key, string $default = ''): string => (string)(function_exists('runtime_env')
    ? runtime_env($key, $default)
    : (getenv($key) ?: $default));

$secure = $env('SESSION_SECURE', 'false') === 'true';

return [
    'name' => $env('SESSION_NAME', 'traxter_session'),
    'secure' => $secure,
    'samesite' => $env('SESSION_SAMESITE', 'Lax'),
];

