<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ActionLogRepository;

final class AuditLogService
{
    public function __construct(private readonly ActionLogRepository $logs = new ActionLogRepository())
    {
    }

    public function record(
        ?int $tenantId,
        ?int $userId,
        string $entityType,
        ?int $entityId,
        string $action,
        array $meta = []
    ): void {
        $this->logs->create($tenantId, $userId, $entityType, $entityId, $action, $meta);
    }
}
