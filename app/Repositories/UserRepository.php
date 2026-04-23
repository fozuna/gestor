<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\DB;
use PDO;

final class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $sql = 'SELECT id, email, password_hash FROM users WHERE email = :email LIMIT 1';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':email', $email, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(string $email, string $passwordHash): int
    {
        $sql = 'INSERT INTO users (email, password_hash) VALUES (:email, :ph)';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':email', $email, PDO::PARAM_STR);
        $st->bindValue(':ph', $passwordHash, PDO::PARAM_STR);
        $st->execute();
        return (int)DB::pdo()->lastInsertId();
    }

    public function listByTenant(int $tenantId): array
    {
        $sql = 'SELECT u.id, u.name, u.email, m.role
                FROM memberships m
                INNER JOIN users u ON u.id = m.user_id
                WHERE m.tenant_id = :tenant_id
                ORDER BY u.name ASC';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}

