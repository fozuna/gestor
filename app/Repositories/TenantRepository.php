<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\DB;
use PDO;

final class TenantRepository
{
    public function listByUserId(int $userId): array
    {
        $sql = 'SELECT t.id, t.name FROM tenants t INNER JOIN memberships m ON m.tenant_id = t.id WHERE m.user_id = :uid ORDER BY t.name';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':uid', $userId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function create(string $name): int
    {
        $sql = 'INSERT INTO tenants (name) VALUES (:name)';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->execute();
        return (int)DB::pdo()->lastInsertId();
    }
}

