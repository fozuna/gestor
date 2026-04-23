<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\DB;
use PDO;

final class MembershipRepository
{
    public function listForUser(int $userId): array
    {
        $sql = 'SELECT m.tenant_id, m.role, t.name AS tenant_name FROM memberships m INNER JOIN tenants t ON t.id = m.tenant_id WHERE m.user_id = :uid ORDER BY t.name';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':uid', $userId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function findRole(int $userId, int $tenantId): ?string
    {
        $sql = 'SELECT role FROM memberships WHERE user_id = :uid AND tenant_id = :tid LIMIT 1';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':uid', $userId, PDO::PARAM_INT);
        $st->bindValue(':tid', $tenantId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        $role = is_array($row) ? ($row['role'] ?? null) : null;
        return is_string($role) ? $role : null;
    }

    public function create(int $userId, int $tenantId, string $role): void
    {
        $sql = 'INSERT INTO memberships (tenant_id, user_id, role) VALUES (:tid, :uid, :role)';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tid', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':uid', $userId, PDO::PARAM_INT);
        $st->bindValue(':role', $role, PDO::PARAM_STR);
        $st->execute();
    }
}

