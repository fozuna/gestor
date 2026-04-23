<?php
declare(strict_types=1);

use Dotenv\Dotenv;

if (!defined('APP_BASE_PATH')) {
    define('APP_BASE_PATH', dirname(__DIR__));
}
if (!defined('APP_PUBLIC_PATH')) {
    define('APP_PUBLIC_PATH', APP_BASE_PATH . DIRECTORY_SEPARATOR . 'public');
}
if (!defined('APP_STORAGE_PATH')) {
    define('APP_STORAGE_PATH', APP_BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
}
if (!defined('APP_CONFIG_PATH')) {
    define('APP_CONFIG_PATH', APP_BASE_PATH . DIRECTORY_SEPARATOR . 'config');
}

if (!function_exists('runtime_env')) {
    function runtime_env(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        return $default;
    }
}

$runtimeFallback = [];
$fallbackPath = APP_CONFIG_PATH . DIRECTORY_SEPARATOR . 'config.php';
if (is_file($fallbackPath)) {
    $loaded = require $fallbackPath;
    if (is_array($loaded)) {
        $runtimeFallback = $loaded;
    }
}

$envPath = APP_BASE_PATH . DIRECTORY_SEPARATOR . '.env';
if (class_exists(Dotenv::class) && is_file($envPath)) {
    try {
        Dotenv::createUnsafeImmutable(APP_BASE_PATH)->safeLoad();
    } catch (Throwable $e) {
        error_log('[bootstrap/runtime] Falha ao carregar .env: ' . $e->getMessage());
    }
}

$map = [
    'APP_NAME' => $runtimeFallback['app']['name'] ?? null,
    'APP_ENV' => $runtimeFallback['app']['env'] ?? null,
    'APP_URL' => $runtimeFallback['app']['url'] ?? null,
    'APP_KEY' => $runtimeFallback['app']['key'] ?? null,
    'APP_TIMEZONE' => $runtimeFallback['app']['timezone'] ?? null,
    'DB_HOST' => $runtimeFallback['database']['host'] ?? null,
    'DB_PORT' => $runtimeFallback['database']['port'] ?? null,
    'DB_DATABASE' => $runtimeFallback['database']['database'] ?? null,
    'DB_USERNAME' => $runtimeFallback['database']['username'] ?? null,
    'DB_PASSWORD' => $runtimeFallback['database']['password'] ?? null,
    'SESSION_NAME' => $runtimeFallback['session']['name'] ?? null,
    'SESSION_SECURE' => $runtimeFallback['session']['secure'] ?? null,
    'SESSION_SAMESITE' => $runtimeFallback['session']['samesite'] ?? null,
    'RECEIPT_PDF_RENDERER' => $runtimeFallback['receipts']['renderer'] ?? null,
];

foreach ($map as $key => $value) {
    if ($value === null || runtime_env($key, null) !== null) {
        continue;
    }

    $stringValue = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
    $_ENV[$key] = $stringValue;
    putenv($key . '=' . $stringValue);
}

$appTimezone = (string)runtime_env('APP_TIMEZONE', 'America/Campo_Grande');
if (!in_array($appTimezone, timezone_identifiers_list(), true)) {
    $appTimezone = 'America/Campo_Grande';
}
date_default_timezone_set($appTimezone);
