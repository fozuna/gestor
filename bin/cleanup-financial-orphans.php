<?php
declare(strict_types=1);

use App\Helpers\DB;

require dirname(__DIR__) . '/bootstrap/app.php';

$execute = in_array('--execute', $argv, true);
$pdo = DB::pdo();

$queries = [
    'invoices_orphan_project' => [
        'select' => 'SELECT i.* FROM invoices i LEFT JOIN projects p ON p.id = i.project_id WHERE i.project_id IS NOT NULL AND p.id IS NULL',
        'delete' => 'DELETE i FROM invoices i LEFT JOIN projects p ON p.id = i.project_id WHERE i.project_id IS NOT NULL AND p.id IS NULL',
    ],
    'invoices_detached_project_financial' => [
        'select' => 'SELECT i.* FROM invoices i WHERE i.project_id IS NULL AND i.installment_type IN ("entry","monthly") AND i.code LIKE "PRJ-%"',
        'delete' => 'DELETE FROM invoices WHERE project_id IS NULL AND installment_type IN ("entry","monthly") AND code LIKE "PRJ-%"',
    ],
    'payments_orphan_invoice' => [
        'select' => 'SELECT p.* FROM payments p LEFT JOIN invoices i ON i.id = p.invoice_id WHERE i.id IS NULL',
        'delete' => 'DELETE p FROM payments p LEFT JOIN invoices i ON i.id = p.invoice_id WHERE i.id IS NULL',
    ],
    'financial_entries_orphan_project' => [
        'select' => 'SELECT fe.* FROM financial_entries fe LEFT JOIN projects p ON p.id = fe.project_id WHERE fe.project_id IS NOT NULL AND p.id IS NULL',
        'delete' => 'DELETE fe FROM financial_entries fe LEFT JOIN projects p ON p.id = fe.project_id WHERE fe.project_id IS NOT NULL AND p.id IS NULL',
    ],
    'financial_entries_orphan_invoice' => [
        'select' => 'SELECT fe.* FROM financial_entries fe LEFT JOIN invoices i ON i.id = fe.invoice_id WHERE fe.invoice_id IS NOT NULL AND i.id IS NULL',
        'delete' => 'DELETE fe FROM financial_entries fe LEFT JOIN invoices i ON i.id = fe.invoice_id WHERE fe.invoice_id IS NOT NULL AND i.id IS NULL',
    ],
    'installment_plans_orphan_project' => [
        'select' => 'SELECT ip.* FROM installment_plans ip LEFT JOIN projects p ON p.id = ip.project_id WHERE p.id IS NULL',
        'delete' => 'DELETE ip FROM installment_plans ip LEFT JOIN projects p ON p.id = ip.project_id WHERE p.id IS NULL',
    ],
];

// Tabelas opcionais que podem existir em algumas instalações legadas.
$optionalTables = [
    'budgets' => ['alias' => 'b', 'project_col' => 'project_id'],
    'cost_centers' => ['alias' => 'cc', 'project_col' => 'project_id'],
];

foreach ($optionalTables as $table => $meta) {
    $existsSt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table
           AND column_name = :col'
    );
    $existsSt->execute([
        ':table' => $table,
        ':col' => $meta['project_col'],
    ]);
    if ((int)$existsSt->fetchColumn() === 0) {
        continue;
    }

    $alias = $meta['alias'];
    $projectCol = $meta['project_col'];
    $key = $table . '_orphan_project';
    $queries[$key] = [
        'select' => "SELECT {$alias}.* FROM {$table} {$alias} LEFT JOIN projects p ON p.id = {$alias}.{$projectCol} WHERE {$alias}.{$projectCol} IS NOT NULL AND p.id IS NULL",
        'delete' => "DELETE {$alias} FROM {$table} {$alias} LEFT JOIN projects p ON p.id = {$alias}.{$projectCol} WHERE {$alias}.{$projectCol} IS NOT NULL AND p.id IS NULL",
    ];
}

$report = [
    'mode' => $execute ? 'execute' : 'dry-run',
    'generated_at' => date('c'),
    'orphans_found' => [],
    'removed' => [],
];
$backupRows = [];

foreach ($queries as $key => $sqls) {
    $rows = $pdo->query($sqls['select'])->fetchAll(PDO::FETCH_ASSOC);
    $report['orphans_found'][$key] = is_array($rows) ? count($rows) : 0;
    $backupRows[$key] = $rows;
}

if ($execute) {
    $backupDir = dirname(__DIR__) . '/storage/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0775, true);
    }
    $backupFile = $backupDir . '/financial-orphans-' . date('Ymd-His') . '.json';
    file_put_contents(
        $backupFile,
        json_encode([
            'generated_at' => date('c'),
            'records' => $backupRows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    $pdo->beginTransaction();
    try {
        foreach ($queries as $key => $sqls) {
            $deleted = $pdo->exec($sqls['delete']);
            $report['removed'][$key] = is_int($deleted) ? $deleted : 0;
        }
        $pdo->commit();
        $report['backup_file'] = $backupFile;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, '[cleanup-financial-orphans] ERRO: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
