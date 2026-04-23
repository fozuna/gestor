<?php
declare(strict_types=1);

use App\Helpers\DB;

require dirname(__DIR__) . '/bootstrap/app.php';

$tenantId = (int)($argv[1] ?? 1);
$pdo = DB::pdo();

$tables = [
    'clients',
    'projects',
    'tasks',
    'invoices',
    'payments',
    'financial_entries',
    'action_logs',
];

$snapshot = [
    'tenant_id' => $tenantId,
    'generated_at' => date(DATE_ATOM),
    'tables' => [],
];

foreach ($tables as $table) {
    $st = $pdo->prepare("SELECT * FROM {$table} WHERE tenant_id = :tenant_id");
    $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $st->execute();
    $snapshot['tables'][$table] = $st->fetchAll();
}

$baseDir = dirname(__DIR__) . '/storage/backups/manual';
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0775, true);
}

$file = $baseDir . '/tenant-' . $tenantId . '-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo 'Backup criado em: ' . $file . PHP_EOL;
