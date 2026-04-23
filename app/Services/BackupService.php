<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Path;

final class BackupService
{
    public function storeFinancialSnapshot(int $tenantId, string $context, array $payload): string
    {
        $baseDir = Path::storage('backups/' . date('Ymd'));
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }

        $file = sprintf(
            '%s/%s-tenant-%d-%s.json',
            $baseDir,
            $context,
            $tenantId,
            date('His')
        );

        $snapshot = [
            'tenant_id' => $tenantId,
            'context' => $context,
            'generated_at' => date(DATE_ATOM),
            'payload' => $payload,
        ];

        file_put_contents($file, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $file;
    }
}
