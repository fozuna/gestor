<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\DB;
use App\Services\ProjectService;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

final class ProjectDetailIntegrationTest extends TestCase
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

    public function testDetailReturnsProjectInstallmentsAndTasks(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente Projeto Detalhe');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto Detalhado');
        $this->createInvoice($tenantId, $clientId, $projectId, 'DET-001', 500.00);
        $this->createTask($tenantId, $projectId, 'Tarefa cobrável', 'billable', 180.00);

        $detail = (new ProjectService())->detail($tenantId, $projectId);

        self::assertNotNull($detail);
        self::assertSame('Projeto Detalhado', $detail['project']['name']);
        self::assertCount(1, $detail['installments']);
        self::assertCount(1, $detail['tasks']);
        self::assertSame(180.0, $detail['billable_tasks_total']);
    }

    public function testDetailDoesNotDuplicateFinancialTotalsWhenProjectHasMultipleTasksAndInvoices(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente Sem Duplicidade');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto Sem Duplicidade', 8000.00);
        $this->createInvoiceWithPaid($tenantId, $clientId, $projectId, 'DET-001', 4000.00, 4000.00, 'paid');
        $this->createInvoiceWithPaid($tenantId, $clientId, $projectId, 'DET-002', 4000.00, 0.00, 'pending');
        $this->createTask($tenantId, $projectId, 'Task A', 'informative', 0.00);
        $this->createTask($tenantId, $projectId, 'Task B', 'informative', 0.00);

        $detail = (new ProjectService())->detail($tenantId, $projectId);

        self::assertNotNull($detail);
        self::assertSame(4000.0, (float)$detail['project']['paid_amount']);
        self::assertSame(4000.0, (float)$detail['project']['pending_amount']);
        self::assertSame(8000.0, (float)$detail['project']['contract_value']);
    }

    public function testDetailClampsPaidAndPendingWhenRawDataExceedsContractValue(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente Clamp');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto Clamp', 8000.00);
        $this->createInvoiceWithPaid($tenantId, $clientId, $projectId, 'DET-CLAMP-1', 20000.00, 20000.00, 'paid');
        $this->createInvoiceWithPaid($tenantId, $clientId, $projectId, 'DET-CLAMP-2', 20000.00, 0.00, 'pending');

        $detail = (new ProjectService())->detail($tenantId, $projectId);

        self::assertNotNull($detail);
        self::assertSame(8000.0, (float)$detail['project']['contract_value']);
        self::assertSame(8000.0, (float)$detail['project']['paid_amount']);
        self::assertSame(0.0, (float)$detail['project']['pending_amount']);
    }

    private function createClient(int $tenantId, string $name): int
    {
        $st = $this->pdo->prepare('INSERT INTO clients (tenant_id, name, email, status) VALUES (:tenant_id, :name, :email, :status)');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':email', uniqid('project-detail-', true) . '@example.com', PDO::PARAM_STR);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function createProject(int $tenantId, int $clientId, string $name, float $contractValue = 1500.00): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO projects (
                tenant_id, client_id, name, status, contract_value, entry_amount, installments_count, created_by_user_id
            ) VALUES (
                :tenant_id, :client_id, :name, :status, :contract_value, :entry_amount, :installments_count, :created_by_user_id
            )'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->bindValue(':contract_value', $contractValue);
        $st->bindValue(':entry_amount', 0.00);
        $st->bindValue(':installments_count', 1, PDO::PARAM_INT);
        $st->bindValue(':created_by_user_id', 1, PDO::PARAM_INT);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function createInvoice(int $tenantId, int $clientId, int $projectId, string $code, float $amount): void
    {
        $this->createInvoiceWithPaid($tenantId, $clientId, $projectId, $code, $amount, 0.00, 'pending');
    }

    private function createInvoiceWithPaid(
        int $tenantId,
        int $clientId,
        int $projectId,
        string $code,
        float $amountTotal,
        float $amountPaid,
        string $status
    ): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO invoices (
                tenant_id, client_id, project_id, code, description, amount_total, amount_paid, status,
                installment_type, installment_number, reference_month, reference_year, due_date, created_by_user_id
            ) VALUES (
                :tenant_id, :client_id, :project_id, :code, :description, :amount_total, :amount_paid, :status,
                :installment_type, :installment_number, :reference_month, :reference_year, :due_date, :created_by_user_id
            )'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->bindValue(':code', $code, PDO::PARAM_STR);
        $st->bindValue(':description', 'Parcela detalhada', PDO::PARAM_STR);
        $st->bindValue(':amount_total', $amountTotal);
        $st->bindValue(':amount_paid', $amountPaid);
        $st->bindValue(':status', $status, PDO::PARAM_STR);
        $st->bindValue(':installment_type', 'monthly', PDO::PARAM_STR);
        $st->bindValue(':installment_number', 1, PDO::PARAM_INT);
        $st->bindValue(':reference_month', 4, PDO::PARAM_INT);
        $st->bindValue(':reference_year', 2026, PDO::PARAM_INT);
        $st->bindValue(':due_date', '2026-04-15', PDO::PARAM_STR);
        $st->bindValue(':created_by_user_id', 1, PDO::PARAM_INT);
        $st->execute();
    }

    private function createTask(int $tenantId, int $projectId, string $title, string $billingType, float $amount): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO tasks (
                tenant_id, project_id, title, status, priority, task_kind, billing_type, billable_amount
            ) VALUES (
                :tenant_id, :project_id, :title, :status, :priority, :task_kind, :billing_type, :billable_amount
            )'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->bindValue(':title', $title, PDO::PARAM_STR);
        $st->bindValue(':status', 'todo', PDO::PARAM_STR);
        $st->bindValue(':priority', 'medium', PDO::PARAM_STR);
        $st->bindValue(':task_kind', 'one_off', PDO::PARAM_STR);
        $st->bindValue(':billing_type', $billingType, PDO::PARAM_STR);
        $st->bindValue(':billable_amount', $amount);
        $st->execute();
    }
}
