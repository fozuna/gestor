<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\DB;
use App\Helpers\Security;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use RuntimeException;
use Throwable;

final class TaskService
{
    public function __construct(
        private readonly TaskRepository $tasks = new TaskRepository(),
        private readonly ProjectRepository $projects = new ProjectRepository(),
        private readonly TaskClassificationService $classification = new TaskClassificationService(),
        private readonly AuditLogService $audit = new AuditLogService()
    ) {
    }

    public function boardByTenant(int $tenantId, string $client = ''): array
    {
        return $this->tasks->boardByTenant($tenantId, $client);
    }

    public function listByClient(int $tenantId, int $clientId, ?string $startDate = null, ?string $endDate = null): array
    {
        return $this->tasks->listByClient($tenantId, $clientId, $startDate, $endDate);
    }

    public function paginatedList(int $tenantId, array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(20, max(5, (int)($filters['per_page'] ?? 10)));
        $status = trim((string)($filters['status'] ?? ''));
        $priority = trim((string)($filters['priority'] ?? ''));
        $assignee = trim((string)($filters['assignee'] ?? ''));
        $client = trim((string)($filters['client'] ?? ''));
        $sort = trim((string)($filters['sort'] ?? 'created_at'));
        $offset = ($page - 1) * $perPage;

        $items = $this->tasks->paginatedListByTenant($tenantId, $status, $priority, $assignee, $client, $sort, $perPage, $offset);
        $total = $this->tasks->countByTenantFilters($tenantId, $status, $priority, $assignee, $client);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => max(1, (int)ceil($total / $perPage)),
            ],
        ];
    }

    public function detail(int $tenantId, int $taskId): ?array
    {
        return $this->tasks->findById($tenantId, $taskId);
    }

    public function listByProjectPaginated(int $tenantId, int $projectId, string $sort = 'created_at', int $limit = 50): array
    {
        return $this->tasks->listByProjectPaginated($tenantId, $projectId, $sort, $limit);
    }

    public function create(int $tenantId, int $projectId, ?int $userId, array $data): int
    {
        $normalized = $this->normalizePayload($tenantId, $projectId, $data);

        $taskId = $this->tasks->create($tenantId, $projectId, $userId, $normalized);
        $this->audit->record($tenantId, $userId, 'task', $taskId, 'task.created', [
            'project_id' => $projectId,
            'status' => $normalized['status'],
            'priority' => $normalized['priority'],
        ]);

        return $taskId;
    }

    public function update(int $tenantId, int $taskId, ?int $userId, array $data): void
    {
        $current = $this->tasks->findById($tenantId, $taskId);
        if ($current === null) {
            throw new RuntimeException('Tarefa não encontrada.');
        }

        $projectId = (int)($data['project_id'] ?? $current['project_id'] ?? 0);
        $normalized = $this->normalizePayload($tenantId, $projectId, $data);
        $normalized['project_id'] = $projectId;

        $this->tasks->update($tenantId, $taskId, $normalized);
        $this->audit->record($tenantId, $userId, 'task', $taskId, 'task.updated', [
            'project_id' => $projectId,
            'status' => $normalized['status'],
            'priority' => $normalized['priority'],
        ]);
    }

    public function delete(int $tenantId, int $taskId, ?int $userId): void
    {
        $current = $this->tasks->findById($tenantId, $taskId);
        if ($current === null) {
            throw new RuntimeException('Tarefa não encontrada.');
        }

        $this->tasks->delete($tenantId, $taskId);
        $this->audit->record($tenantId, $userId, 'task', $taskId, 'task.deleted', [
            'project_id' => $current['project_id'] ?? null,
            'title' => $current['title'] ?? '',
        ]);
    }

    public function move(int $tenantId, int $taskId, ?int $userId, string $status, array $orderedIds): void
    {
        if (!in_array($status, ['todo', 'doing', 'done'], true)) {
            throw new RuntimeException('Status de Kanban inválido.');
        }

        $current = $this->tasks->findById($tenantId, $taskId);
        if ($current === null) {
            throw new RuntimeException('Tarefa não encontrada.');
        }

        $pdo = DB::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $ids = array_values(array_filter(array_map('intval', $orderedIds), static fn(int $id): bool => $id > 0));
            if (!in_array($taskId, $ids, true)) {
                $ids[] = $taskId;
            }

            $this->tasks->rebalanceStatusPositions($tenantId, $status, $ids);
            $this->audit->record($tenantId, $userId, 'task', $taskId, 'task.kanban.moved', [
                'from_status' => $current['status'] ?? 'todo',
                'to_status' => $status,
                'ordered_ids' => $ids,
            ]);
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function normalizePayload(int $tenantId, int $projectId, array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        $status = (string)($data['status'] ?? 'todo');
        $priority = (string)($data['priority'] ?? 'medium');
        $taskKind = (string)($data['task_kind'] ?? 'in_scope');
        $dueDateRaw = trim((string)($data['due_date'] ?? ''));
        $dueDate = $dueDateRaw !== '' ? Security::parseDate($dueDateRaw) : null;

        if ($projectId <= 0) {
            throw new RuntimeException('Selecione um projeto para a tarefa.');
        }

        if ($title === '') {
            throw new RuntimeException('Informe o título da tarefa.');
        }

        if (!in_array($status, ['todo', 'doing', 'done'], true)) {
            throw new RuntimeException('Status de tarefa inválido.');
        }

        if (!in_array($priority, ['low', 'medium', 'high'], true)) {
            throw new RuntimeException('Prioridade de tarefa inválida.');
        }

        if ($dueDateRaw !== '' && $dueDate === null) {
            throw new RuntimeException('Data de prazo inválida. Use o formato dd/mm/aaaa.');
        }
        if ($this->projects->findById($tenantId, $projectId) === null) {
            throw new RuntimeException('Projeto inválido para vincular a tarefa.');
        }

        $billing = $this->classification->normalize(
            $taskKind,
            Security::parseMoney((string)($data['billable_amount'] ?? '0'))
        );

        $assigneeUserId = (int)($data['assignee_user_id'] ?? 0);

        return [
            'title' => $title,
            'description' => trim((string)($data['description'] ?? '')),
            'status' => $status,
            'priority' => $priority,
            'task_kind' => $billing['task_kind'],
            'billing_type' => $billing['billing_type'],
            'billable_amount' => $billing['billable_amount'],
            'assignee_user_id' => $assigneeUserId > 0 ? $assigneeUserId : 0,
            'due_date' => $dueDate ?? '',
            'project_id' => $projectId,
        ];
    }
}
