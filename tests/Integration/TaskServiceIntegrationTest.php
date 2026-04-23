<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\DB;
use App\Services\TaskService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

final class TaskServiceIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = DB::pdo();
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        if ($this->pdo instanceof PDO && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        parent::tearDown();
    }

    public function testCreateConvertsBrazilianDateBeforeInsert(): void
    {
        $tenantId = 1;
        $projectId = $this->createProjectFixture($tenantId, 'Projeto tarefa BR');

        $taskId = (new TaskService())->create($tenantId, $projectId, 1, [
            'title' => 'Task BR',
            'status' => 'todo',
            'priority' => 'medium',
            'task_kind' => 'in_scope',
            'billable_amount' => '0',
            'due_date' => '20/04/2026',
        ]);

        self::assertGreaterThan(0, $taskId);
        self::assertSame('2026-04-20', $this->fetchTaskDueDate($taskId));
    }

    public function testCreateAcceptsIsoDate(): void
    {
        $tenantId = 1;
        $projectId = $this->createProjectFixture($tenantId, 'Projeto tarefa ISO');

        $taskId = (new TaskService())->create($tenantId, $projectId, 1, [
            'title' => 'Task ISO',
            'status' => 'todo',
            'priority' => 'medium',
            'task_kind' => 'in_scope',
            'billable_amount' => '0',
            'due_date' => '2026-04-20',
        ]);

        self::assertSame('2026-04-20', $this->fetchTaskDueDate($taskId));
    }

    public function testCreateAllowsEmptyDate(): void
    {
        $tenantId = 1;
        $projectId = $this->createProjectFixture($tenantId, 'Projeto tarefa sem prazo');

        $taskId = (new TaskService())->create($tenantId, $projectId, 1, [
            'title' => 'Task sem prazo',
            'status' => 'todo',
            'priority' => 'medium',
            'task_kind' => 'in_scope',
            'billable_amount' => '0',
            'due_date' => '',
        ]);

        self::assertNull($this->fetchTaskDueDate($taskId));
    }

    public function testCreateRejectsInvalidDateWithoutPersisting(): void
    {
        $tenantId = 1;
        $projectId = $this->createProjectFixture($tenantId, 'Projeto tarefa inválida');
        $before = $this->countTasks();

        try {
            (new TaskService())->create($tenantId, $projectId, 1, [
                'title' => 'Task inválida',
                'status' => 'todo',
                'priority' => 'medium',
                'task_kind' => 'in_scope',
                'billable_amount' => '0',
                'due_date' => '32/13/2026',
            ]);
            self::fail('Era esperado erro para data inválida.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Data de prazo inválida', $e->getMessage());
        }

        self::assertSame($before, $this->countTasks());
    }

    public function testCreateRejectsProjectFromAnotherTenant(): void
    {
        $tenantId = 1;
        $foreignTenantId = $this->createTenant('Tenant externo task');
        $foreignProjectId = $this->createProjectFixture($foreignTenantId, 'Projeto externo');
        $before = $this->countTasks();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Projeto inválido para vincular a tarefa.');

        try {
            (new TaskService())->create($tenantId, $foreignProjectId, 1, [
                'title' => 'Task com projeto externo',
                'status' => 'todo',
                'priority' => 'medium',
                'task_kind' => 'in_scope',
                'billable_amount' => '0',
                'due_date' => '2026-04-20',
            ]);
        } finally {
            self::assertSame($before, $this->countTasks());
        }
    }

    public function testUpdatePersistsChanges(): void
    {
        $tenantId = 1;
        $projectId = $this->createProjectFixture($tenantId, 'Projeto update task');
        $taskId = (new TaskService())->create($tenantId, $projectId, 1, [
            'title' => 'Task original',
            'status' => 'todo',
            'priority' => 'low',
            'task_kind' => 'in_scope',
            'billable_amount' => '0',
            'due_date' => '2026-04-20',
        ]);

        (new TaskService())->update($tenantId, $taskId, 1, [
            'project_id' => $projectId,
            'title' => 'Task atualizada',
            'description' => 'Descricao atualizada',
            'status' => 'doing',
            'priority' => 'high',
            'task_kind' => 'one_off',
            'billable_amount' => '150,50',
            'assignee_user_id' => 1,
            'due_date' => '22/04/2026',
        ]);

        $task = $this->fetchTask($taskId);
        self::assertSame('Task atualizada', $task['title']);
        self::assertSame('doing', $task['status']);
        self::assertSame('high', $task['priority']);
        self::assertSame('2026-04-22', $task['due_date']);
        self::assertSame('billable', $task['billing_type']);
        self::assertSame('150.50', (string)$task['billable_amount']);
    }

    public function testMovePersistsKanbanStatusAndOrder(): void
    {
        $tenantId = 1;
        $projectId = $this->createProjectFixture($tenantId, 'Projeto kanban');
        $firstTaskId = (new TaskService())->create($tenantId, $projectId, 1, [
            'title' => 'Task 1',
            'status' => 'todo',
            'priority' => 'medium',
            'task_kind' => 'in_scope',
            'billable_amount' => '0',
            'due_date' => '',
        ]);
        $secondTaskId = (new TaskService())->create($tenantId, $projectId, 1, [
            'title' => 'Task 2',
            'status' => 'todo',
            'priority' => 'medium',
            'task_kind' => 'in_scope',
            'billable_amount' => '0',
            'due_date' => '',
        ]);

        (new TaskService())->move($tenantId, $secondTaskId, 1, 'doing', [$secondTaskId]);
        (new TaskService())->move($tenantId, $firstTaskId, 1, 'doing', [$secondTaskId, $firstTaskId]);

        $movedTask = $this->fetchTask($secondTaskId);
        $reorderedTask = $this->fetchTask($firstTaskId);
        self::assertSame('doing', $movedTask['status']);
        self::assertSame('doing', $reorderedTask['status']);
        self::assertSame('1', (string)$movedTask['position']);
        self::assertSame('2', (string)$reorderedTask['position']);
    }

    private function createProjectFixture(int $tenantId, string $projectName): int
    {
        $clientId = $this->createClient($tenantId, $projectName . ' Cliente');

        $st = $this->pdo->prepare(
            'INSERT INTO projects (
                tenant_id, client_id, name, status, contract_value, entry_amount, installments_count, created_by_user_id
            ) VALUES (
                :tenant_id, :client_id, :name, :status, :contract_value, :entry_amount, :installments_count, :created_by_user_id
            )'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':name', $projectName, PDO::PARAM_STR);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->bindValue(':contract_value', 1000.00);
        $st->bindValue(':entry_amount', 0.00);
        $st->bindValue(':installments_count', 1, PDO::PARAM_INT);
        $st->bindValue(':created_by_user_id', 1, PDO::PARAM_INT);
        $st->execute();

        return (int)$this->pdo->lastInsertId();
    }

    private function createClient(int $tenantId, string $name): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO clients (tenant_id, name, email, status) VALUES (:tenant_id, :name, :email, :status)'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':email', uniqid('task-date-', true) . '@example.com', PDO::PARAM_STR);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->execute();

        return (int)$this->pdo->lastInsertId();
    }

    private function createTenant(string $name): int
    {
        $st = $this->pdo->prepare('INSERT INTO tenants (name, slug) VALUES (:name, :slug)');
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':slug', uniqid('tenant-task-', true), PDO::PARAM_STR);
        $st->execute();

        return (int)$this->pdo->lastInsertId();
    }

    private function fetchTaskDueDate(int $taskId): ?string
    {
        $st = $this->pdo->prepare('SELECT due_date FROM tasks WHERE id = :id');
        $st->bindValue(':id', $taskId, PDO::PARAM_INT);
        $st->execute();
        $value = $st->fetchColumn();
        return is_string($value) ? $value : null;
    }

    private function fetchTask(int $taskId): array
    {
        $st = $this->pdo->prepare('SELECT * FROM tasks WHERE id = :id');
        $st->bindValue(':id', $taskId, PDO::PARAM_INT);
        $st->execute();
        $task = $st->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($task);
        return $task;
    }

    private function countTasks(): int
    {
        $value = $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
        return (int)$value;
    }
}
