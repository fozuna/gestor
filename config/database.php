<?php
declare(strict_types=1);

$env = static fn(string $key, string $default = ''): string => (string)(function_exists('runtime_env')
    ? runtime_env($key, $default)
    : (getenv($key) ?: $default));

$host = $env('DB_HOST', '127.0.0.1');
$port = $env('DB_PORT', '3306');
$db = $env('DB_DATABASE', '');

return [
    'dsn' => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db),
    'user' => $env('DB_USERNAME', ''),
    'pass' => $env('DB_PASSWORD', ''),
    'options' => [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ],
];

