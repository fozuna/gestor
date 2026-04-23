<?php
declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createUnsafeImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$appTimezone = (string)($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'America/Campo_Grande');
if (!in_array($appTimezone, timezone_identifiers_list(), true)) {
    $appTimezone = 'America/Campo_Grande';
}
date_default_timezone_set($appTimezone);

require __DIR__ . '/../app/App.php';

