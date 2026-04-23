<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\DB;
use PDO;

final class TaskRepository
{
    public function paginatedListByTenant(
        int $tenantId,
        string $status,
        string $priority,
        string $assignee,
        string $client,
        string $sort,
        int $limit,
        int $offset
    ): array {
        $sql = 'SELECT t.id, t.project_id, t.title, t.description, t.status, t.priority, t.task_kind, t.billing_type,
                       t.billable_amount, t.position, t.assignee_user_id, t.created_by_user_id, t.due_date, t.created_at,
                       p.name AS project_name, c.id AS client_id, c.name AS client_name,
                       u.name AS assignee_name
                FROM tasks t
                INNER JOIN projects p ON p.id = t.project_id
                INNER JOIN clients c ON c.id = p.client_id
                LEFT JOIN users u ON u.id = t.assignee_user_id
                WHERE t.tenant_id = :tenant_id';

        if ($status !== '') {
            $sql .= ' AND t.status = :status';
        }
        if ($priority !== '') {
            $sql .= ' AND t.priority = :priority';
        }
        if ($assignee !== '') {
            if ($assignee === 'unassigned') {
                $sql .= ' AND t.assignee_user_id IS NULL';
            } else {
                $sql .= ' AND t.assignee_user_id = :assignee_user_id';
            }
        }
        if ($client !== '') {
            $sql .= ' AND p.client_id = :client_id';
        }

        $sql .= ' ORDER BY ' . $this->resolveOrderBy($sort) . ' LIMIT :limit OFFSET :offset';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($status !== '') {
            $st->bindValue(':status', $status, PDO::PARAM_STR);
        }
        if ($priority !== '') {
            $st->bindValue(':priority', $priority, PDO::PARAM_STR);
        }
        if ($assignee !== '' && $assignee !== 'unassigned') {
            $st->bindValue(':assignee_user_id', (int)$assignee, PDO::PARAM_INT);
        }
        if ($client !== '') {
            $st->bindValue(':client_id', (int)$client, PDO::PARAM_INT);
        }
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function countByTenantFilters(int $tenantId, string $status, string $priority, string $assignee, string $client): int
    {
        $sql = 'SELECT COUNT(*)
                FROM tasks t
                INNER JOIN projects p ON p.id = t.project_id
                WHERE t.tenant_id = :tenant_id';
        if ($status !== '') {
            $sql .= ' AND t.status = :status';
        }
        if ($priority !== '') {
            $sql .= ' AND t.priority = :priority';
        }
        if ($assignee !== '') {
            if ($assignee === 'unassigned') {
                $sql .= ' AND t.assignee_user_id IS NULL';
            } else {
                $sql .= ' AND t.assignee_user_id = :assignee_user_id';
            }
        }
        if ($client !== '') {
            $sql .= ' AND p.client_id = :client_id';
        }
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($status !== '') {
            $st->bindValue(':status', $status, PDO::PARAM_STR);
        }
        if ($priority !== '') {
            $st->bindValue(':priority', $priority, PDO::PARAM_STR);
        }
        if ($assignee !== '' && $assignee !== 'unassigned') {
            $st->bindValue(':assignee_user_id', (int)$assignee, PDO::PARAM_INT);
        }
        if ($client !== '') {
            $st->bindValue(':client_id', (int)$client, PDO::PARAM_INT);
        }
        $st->execute();
        return (int)$st->fetchColumn();
    }

    public function boardByTenant(int $tenantId, string $client = ''): array
    {
        $sql = 'SELECT t.id, t.title, t.status, t.priority, t.task_kind, t.billing_type, t.billable_amount, t.due_date, t.created_at, t.position,
                       p.name AS project_name, c.id AS client_id, c.name AS client_name, u.name AS assignee_name
                FROM tasks t
                INNER JOIN projects p ON p.id = t.project_id
                INNER JOIN clients c ON c.id = p.client_id
                LEFT JOIN users u ON u.id = t.assignee_user_id
                WHERE t.tenant_id = :tenant_id';
        if ($client !== '') {
            $sql .= ' AND p.client_id = :client_id';
        }
        $sql .= '
                ORDER BY FIELD(t.status, "todo", "doing", "done"), t.position ASC, t.created_at DESC';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($client !== '') {
            $st->bindValue(':client_id', (int)$client, PDO::PARAM_INT);
        }
        $st->execute();
        $rows = $st->fetchAll();

        $board = [
            'todo' => [],
            'doing' => [],
            'done' => [],
        ];

        if (!is_array($rows)) {
            return $board;
        }

        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? 'todo');
            if (!isset($board[$status])) {
                $board[$status] = [];
            }
            $board[$status][] = $row;
        }

        return $board;
    }

    public function create(int $tenantId, int $projectId, ?int $userId, array $data): int
    {
        $position = $this->nextPosition($tenantId, (string)$data['status']);
        $sql = 'INSERT INTO tasks (
                    tenant_id, project_id, title, description, status, priority, task_kind, billing_type, billable_amount,
                    position, assignee_user_id, created_by_user_id, due_date
                ) VALUES (
                    :tenant_id, :project_id, :title, :description, :status, :priority, :task_kind, :billing_type, :billable_amount,
                    :position, :assignee_user_id, :created_by_user_id, :due_date
                )';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $st->bindValue(':description', $data['description'] !== '' ? $data['description'] : null, $data['description'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':status', $data['status'], PDO::PARAM_STR);
        $st->bindValue(':priority', $data['priority'], PDO::PARAM_STR);
        $st->bindValue(':task_kind', $data['task_kind'], PDO::PARAM_STR);
        $st->bindValue(':billing_type', $data['billing_type'], PDO::PARAM_STR);
        $st->bindValue(':billable_amount', $data['billable_amount']);
        $st->bindValue(':position', $position, PDO::PARAM_INT);
        $assigneeUserId = (int)($data['assignee_user_id'] ?? 0);
        $st->bindValue(':assignee_user_id', $assigneeUserId > 0 ? $assigneeUserId : null, $assigneeUserId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':created_by_user_id', $userId ?: null, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':due_date', $data['due_date'] !== '' ? $data['due_date'] : null, $data['due_date'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->execute();
        return (int)DB::pdo()->lastInsertId();
    }

    public function update(int $tenantId, int $taskId, array $data): void
    {
        $sql = 'UPDATE tasks
                SET project_id = :project_id,
                    title = :title,
                    description = :description,
                    status = :status,
                    priority = :priority,
                    task_kind = :task_kind,
                    billing_type = :billing_type,
                    billable_amount = :billable_amount,
                    assignee_user_id = :assignee_user_id,
                    due_date = :due_date
                WHERE tenant_id = :tenant_id
                  AND id = :task_id';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':project_id', $data['project_id'], PDO::PARAM_INT);
        $st->bindValue(':title', $data['title'], PDO::PARAM_STR);
        $st->bindValue(':description', $data['description'] !== '' ? $data['description'] : null, $data['description'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':status', $data['status'], PDO::PARAM_STR);
        $st->bindValue(':priority', $data['priority'], PDO::PARAM_STR);
        $st->bindValue(':task_kind', $data['task_kind'], PDO::PARAM_STR);
        $st->bindValue(':billing_type', $data['billing_type'], PDO::PARAM_STR);
        $st->bindValue(':billable_amount', $data['billable_amount']);
        $assigneeUserId = (int)($data['assignee_user_id'] ?? 0);
        $st->bindValue(':assignee_user_id', $assigneeUserId > 0 ? $assigneeUserId : null, $assigneeUserId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':due_date', $data['due_date'] !== '' ? $data['due_date'] : null, $data['due_date'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':task_id', $taskId, PDO::PARAM_INT);
        $st->execute();
    }

    public function delete(int $tenantId, int $taskId): void
    {
        $st = DB::pdo()->prepare('DELETE FROM tasks WHERE tenant_id = :tenant_id AND id = :task_id');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':task_id', $taskId, PDO::PARAM_INT);
        $st->execute();
    }

    public function listByClient(int $tenantId, int $clientId, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = 'SELECT t.id, t.title, t.status, t.priority, t.task_kind, t.billing_type, t.billable_amount, t.due_date, t.created_at,
                       p.name AS project_name, u.name AS assignee_name
                FROM tasks t
                INNER JOIN projects p ON p.id = t.project_id
                LEFT JOIN users u ON u.id = t.assignee_user_id
                WHERE t.tenant_id = :tenant_id
                  AND p.client_id = :client_id';

        $params = [
            ':tenant_id' => $tenantId,
            ':client_id' => $clientId,
        ];

        if ($startDate !== null) {
            $sql .= ' AND DATE(t.created_at) >= :start_date';
            $params[':start_date'] = $startDate;
        }

        if ($endDate !== null) {
            $sql .= ' AND DATE(t.created_at) <= :end_date';
            $params[':end_date'] = $endDate;
        }

        $sql .= ' ORDER BY t.created_at DESC';
        $st = DB::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $st->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function listByProject(int $tenantId, int $projectId): array
    {
        $sql = 'SELECT t.id, t.title, t.status, t.priority, t.task_kind, t.billing_type, t.billable_amount, t.due_date, t.created_at,
                       u.name AS assignee_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.assignee_user_id
                WHERE tenant_id = :tenant_id
                  AND project_id = :project_id
                ORDER BY FIELD(status, "todo", "doing", "done"), created_at DESC';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function listByProjectPaginated(int $tenantId, int $projectId, string $sort, int $limit): array
    {
        $sql = 'SELECT t.id, t.title, t.status, t.priority, t.task_kind, t.billing_type, t.billable_amount, t.due_date, t.created_at,
                       u.name AS assignee_name
                FROM tasks t
                LEFT JOIN users u ON u.id = t.assignee_user_id
                WHERE t.tenant_id = :tenant_id
                  AND t.project_id = :project_id
                ORDER BY ' . $this->resolveProjectOrderBy($sort) . '
                LIMIT :limit';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function findById(int $tenantId, int $taskId): ?array
    {
        $sql = 'SELECT t.*, p.name AS project_name, u.name AS assignee_name
                FROM tasks t
                INNER JOIN projects p ON p.id = t.project_id
                LEFT JOIN users u ON u.id = t.assignee_user_id
                WHERE t.tenant_id = :tenant_id
                  AND t.id = :task_id
                LIMIT 1';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':task_id', $taskId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function move(int $tenantId, int $taskId, string $status, int $position): void
    {
        $sql = 'UPDATE tasks
                SET status = :status, position = :position
                WHERE tenant_id = :tenant_id AND id = :task_id';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':status', $status, PDO::PARAM_STR);
        $st->bindValue(':position', $position, PDO::PARAM_INT);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':task_id', $taskId, PDO::PARAM_INT);
        $st->execute();
    }

    public function rebalanceStatusPositions(int $tenantId, string $status, array $orderedIds): void
    {
        $position = 1;
        foreach ($orderedIds as $taskId) {
            $st = DB::pdo()->prepare('UPDATE tasks SET status = :status, position = :position WHERE tenant_id = :tenant_id AND id = :task_id');
            $st->bindValue(':status', $status, PDO::PARAM_STR);
            $st->bindValue(':position', $position, PDO::PARAM_INT);
            $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
            $st->bindValue(':task_id', (int)$taskId, PDO::PARAM_INT);
            $st->execute();
            $position++;
        }
    }

    private function nextPosition(int $tenantId, string $status): int
    {
        $st = DB::pdo()->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM tasks WHERE tenant_id = :tenant_id AND status = :status');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':status', $status, PDO::PARAM_STR);
        $st->execute();
        return (int)$st->fetchColumn();
    }

    private function resolveOrderBy(string $sort): string
    {
        return match ($sort) {
            'priority' => 'FIELD(t.priority, "high", "medium", "low"), t.created_at DESC',
            'due_date' => 'CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END, t.due_date ASC, t.created_at DESC',
            'assignee' => 'u.name ASC, t.created_at DESC',
            default => 't.created_at DESC',
        };
    }

    private function resolveProjectOrderBy(string $sort): string
    {
        return match ($sort) {
            'priority' => 'FIELD(t.priority, "high", "medium", "low"), t.created_at DESC',
            'due_date' => 'CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END, t.due_date ASC, t.created_at DESC',
            'assignee' => 'u.name ASC, t.created_at DESC',
            default => 't.created_at DESC',
        };
    }
}
