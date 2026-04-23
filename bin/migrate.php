<?php
declare(strict_types=1);

use App\Services\MigrationService;

require dirname(__DIR__) . '/bootstrap/app.php';

try {
    $changes = (new MigrationService())->migrate();
    echo 'Migração concluída.' . PHP_EOL;
    echo 'Alterações aplicadas: ' . count($changes) . PHP_EOL;
    if ($changes !== []) {
        echo implode(PHP_EOL, $changes) . PHP_EOL;
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Falha na migração: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
