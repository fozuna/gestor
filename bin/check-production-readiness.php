<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Helpers\Installer;
use App\Helpers\DB;

$root = dirname(__DIR__);
$checks = [];

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

$checks[] = ['installer instalado', Installer::installed()];

foreach ($checks as [$label, $ok]) {
    echo sprintf("[%s] %s\n", $ok ? 'OK' : 'FAIL', $label);
}
