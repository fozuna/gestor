<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\DB;
use PDO;

final class ActionLogRepository
{
    public function create(
        ?int $tenantId,
        ?int $userId,
        string $entityType,
        ?int $entityId,
        string $action,
        array $meta = []
    ): void {
        $sql = 'INSERT INTO action_logs (tenant_id, user_id, entity_type, entity_id, action, meta_json, ip_address, user_agent)
                VALUES (:tenant_id, :user_id, :entity_type, :entity_id, :action, :meta_json, :ip_address, :user_agent)';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, $tenantId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':user_id', $userId, $userId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
        $st->bindValue(':entity_id', $entityId, $entityId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':action', $action, PDO::PARAM_STR);
        $st->bindValue(':meta_json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
        $st->bindValue(':ip_address', (string)($_SERVER['REMOTE_ADDR'] ?? ''), PDO::PARAM_STR);
        $st->bindValue(':user_agent', substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), PDO::PARAM_STR);
        $st->execute();
    }
}
