<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Helpers\Installer;
use App\Helpers\DB;

$root = dirname(__DIR__);
$checks = [];
$status = Installer::status();

$checks[] = ['.env', is_file($root . '/.env')];
$checks[] = ['storage/', is_dir($root . '/storage')];
$checks[] = ['storage gravavel', is_dir($root . '/storage') && is_writable($root . '/storage')];
$checks[] = ['installed.lock', is_file($root . '/storage/installed.lock')];

try {
    $pdo = DB::pdo();
    $ok = (bool)$pdo->query('SELECT 1')->fetchColumn();
    $checks[] = ['conexao banco', $ok];
} catch (Throwable) {
    $checks[] = ['conexao banco', false];
}

$checks[] = ['installer instalado', (bool)($status['installed'] ?? false)];

foreach ($checks as [$label, $ok]) {
    echo sprintf("[%s] %s\n", $ok ? 'OK' : 'FAIL', $label);
}

echo 'installer reason: ' . (string)($status['reason'] ?? 'unknown') . PHP_EOL;

$runtimeConfig = is_array($status['runtime_config'] ?? null) ? $status['runtime_config'] : [];
if ($runtimeConfig !== []) {
    echo 'runtime config source: ' . (string)($runtimeConfig['source'] ?? 'unknown') . PHP_EOL;
}

$database = is_array($status['database'] ?? null) ? $status['database'] : [];
if (($database['error'] ?? null) !== null) {
    echo 'database error: ' . (string)$database['error'] . PHP_EOL;
}

$missingTables = is_array($database['missing_tables'] ?? null) ? $database['missing_tables'] : [];
if ($missingTables !== []) {
    echo 'missing tables: ' . implode(', ', array_map('strval', $missingTables)) . PHP_EOL;
}
