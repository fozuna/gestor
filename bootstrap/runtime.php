<?php
declare(strict_types=1);

use App\Helpers\RuntimeConfigLoader;
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

$envPath = APP_BASE_PATH . DIRECTORY_SEPARATOR . '.env';
if (class_exists(Dotenv::class) && is_file($envPath)) {
    try {
        Dotenv::createUnsafeImmutable(APP_BASE_PATH)->safeLoad();
    } catch (Throwable $e) {
        error_log('[bootstrap/runtime] Falha ao carregar .env: ' . $e->getMessage());
    }
}

$runtimeSelection = RuntimeConfigLoader::load(
    APP_CONFIG_PATH,
    (string)runtime_env('APP_ENV', ''),
    (string)($_SERVER['HTTP_HOST'] ?? php_uname('n'))
);
$runtimeFallback = is_array($runtimeSelection['config'] ?? null) ? $runtimeSelection['config'] : [];
$_ENV['APP_RUNTIME_ENV'] = (string)($runtimeSelection['environment'] ?? 'production');
$_ENV['APP_RUNTIME_CONFIG_SOURCE'] = (string)($runtimeSelection['source'] ?? 'unknown');
$_ENV['APP_RUNTIME_CONFIG_OVERRIDE'] = (($runtimeSelection['override_used'] ?? false) ? 'true' : 'false');
putenv('APP_RUNTIME_ENV=' . $_ENV['APP_RUNTIME_ENV']);
putenv('APP_RUNTIME_CONFIG_SOURCE=' . $_ENV['APP_RUNTIME_CONFIG_SOURCE']);
putenv('APP_RUNTIME_CONFIG_OVERRIDE=' . $_ENV['APP_RUNTIME_CONFIG_OVERRIDE']);

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
