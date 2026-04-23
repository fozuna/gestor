<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\DB;
use PDO;

final class ClientRepository
{
    public function listByTenant(int $tenantId): array
    {
        $sql = 'SELECT c.id, c.name, c.email, c.phone, c.status, c.created_at, COUNT(p.id) AS projects_count
                FROM clients c
                LEFT JOIN projects p ON p.client_id = c.id
                WHERE c.tenant_id = :tenant_id
                GROUP BY c.id, c.name, c.email, c.phone, c.status, c.created_at
                ORDER BY c.created_at DESC';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function create(int $tenantId, array $data): int
    {
        $sql = 'INSERT INTO clients (tenant_id, name, email, phone, status, notes)
                VALUES (:tenant_id, :name, :email, :phone, :status, :notes)';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $st->bindValue(':email', $data['email'] !== '' ? $data['email'] : null, $data['email'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':phone', $data['phone'] !== '' ? $data['phone'] : null, $data['phone'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':status', $data['status'], PDO::PARAM_STR);
        $st->bindValue(':notes', $data['notes'] !== '' ? $data['notes'] : null, $data['notes'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->execute();
        return (int)DB::pdo()->lastInsertId();
    }

    public function existsForTenant(int $tenantId, string $name): bool
    {
        $sql = 'SELECT id FROM clients WHERE tenant_id = :tenant_id AND name = :name LIMIT 1';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->execute();
        return $st->fetchColumn() !== false;
    }

    public function optionsByTenant(int $tenantId): array
    {
        $sql = 'SELECT id, name FROM clients WHERE tenant_id = :tenant_id AND status = :status ORDER BY name';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function findById(int $tenantId, int $clientId): ?array
    {
        $sql = 'SELECT id, name, legal_name, document_number, email, phone, status, notes, created_at
                FROM clients
                WHERE tenant_id = :tenant_id AND id = :client_id
                LIMIT 1';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function update(int $tenantId, int $clientId, array $data): void
    {
        $sql = 'UPDATE clients
                SET name = :name,
                    email = :email,
                    phone = :phone,
                    status = :status,
                    notes = :notes
                WHERE tenant_id = :tenant_id
                  AND id = :client_id';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $st->bindValue(':email', $data['email'] !== '' ? $data['email'] : null, $data['email'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':phone', $data['phone'] !== '' ? $data['phone'] : null, $data['phone'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':status', $data['status'], PDO::PARAM_STR);
        $st->bindValue(':notes', $data['notes'] !== '' ? $data['notes'] : null, $data['notes'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->execute();
    }

    public function delete(int $tenantId, int $clientId): int
    {
        $st = DB::pdo()->prepare('DELETE FROM clients WHERE tenant_id = :tenant_id AND id = :client_id');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->execute();
        return $st->rowCount();
    }

    public function hasActiveProjects(int $tenantId, int $clientId): bool
    {
        $st = DB::pdo()->prepare(
            'SELECT 1
             FROM projects
             WHERE tenant_id = :tenant_id
               AND client_id = :client_id
               AND status = :status
             LIMIT 1'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->execute();
        return $st->fetchColumn() !== false;
    }
}
