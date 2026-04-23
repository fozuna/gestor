<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_DATABASE') ?: '';

return [
    'dsn' => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db),
    'user' => getenv('DB_USERNAME') ?: '',
    'pass' => getenv('DB_PASSWORD') ?: '',
    'options' => [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ],
];

