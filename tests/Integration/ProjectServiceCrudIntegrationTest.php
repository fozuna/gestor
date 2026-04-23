<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\DB;
use App\Services\ProjectService;
use App\Services\MigrationService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

final class ProjectServiceCrudIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        (new MigrationService())->migrate();
    }

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

    public function testCreateUpdateAndDeleteBasicProject(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente CRUD projeto');
        $service = new ProjectService();

        $projectId = $service->createBasic($tenantId, $clientId, 1, [
            'name' => 'Projeto modulo',
            'description' => 'Descricao inicial',
            'status' => 'active',
            'start_date' => '2026-04-10',
            'due_date' => '2026-05-10',
            'contract_value' => '2500,00',
            'entry_amount' => '500,00',
            'payment_terms' => '30 dias',
            'installments_count' => 2,
            'first_installment_date' => '2026-04-15',
        ]);

        self::assertGreaterThan(0, $projectId);
        self::assertSame('Projeto modulo', $this->fetchProjectName($projectId));

        $service->updateBasic($tenantId, $projectId, $clientId, 1, [
            'name' => 'Projeto modulo atualizado',
            'description' => 'Descricao final',
            'status' => 'paused',
            'start_date' => '2026-04-12',
            'due_date' => '2026-05-12',
            'contract_value' => '3000,00',
            'entry_amount' => '600,00',
            'payment_terms' => '45 dias',
            'installments_count' => 3,
            'first_installment_date' => '2026-04-20',
        ]);

        $project = $this->fetchProject($projectId);
        self::assertSame('Projeto modulo atualizado', $project['name']);
        self::assertSame('paused', $project['status']);
        self::assertSame('2026-05-12', $project['due_date']);

        $service->deleteBasic($tenantId, $projectId, 1);
        self::assertNull($this->findProjectRow($projectId));
    }

    public function testPaginatedListFiltersBySearchAndStatus(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente filtros projeto');
        $service = new ProjectService();

        $service->createBasic($tenantId, $clientId, 1, [
            'name' => 'Projeto Alpha',
            'description' => '',
            'status' => 'active',
            'start_date' => '',
            'due_date' => '',
            'contract_value' => '0',
            'entry_amount' => '0',
            'payment_terms' => '',
            'installments_count' => 0,
            'first_installment_date' => '',
        ]);
        $service->createBasic($tenantId, $clientId, 1, [
            'name' => 'Projeto Beta',
            'description' => '',
            'status' => 'done',
            'start_date' => '',
            'due_date' => '',
            'contract_value' => '0',
            'entry_amount' => '0',
            'payment_terms' => '',
            'installments_count' => 0,
            'first_installment_date' => '',
        ]);

        $result = $service->paginatedList($tenantId, [
            'search' => 'Alpha',
            'status' => 'active',
            'page' => 1,
            'per_page' => 10,
        ]);

        self::assertCount(1, $result['items']);
        self::assertSame('Projeto Alpha', $result['items'][0]['name']);
        self::assertSame(1, $result['pagination']['total']);
    }

    public function testDeleteRemovesAllFinancialRecordsInCascade(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente cascade');
        $projectId = (new ProjectService())->createBasic($tenantId, $clientId, 1, [
            'name' => 'Projeto cascade',
            'description' => '',
            'status' => 'active',
            'start_date' => '',
            'due_date' => '',
            'contract_value' => '1000,00',
            'entry_amount' => '0',
            'payment_terms' => '',
            'installments_count' => 0,
            'first_installment_date' => '',
        ]);

        $invoiceId = $this->createInvoice($tenantId, $clientId, $projectId, 'pending', 0.00);
        $this->createPayment($tenantId, $invoiceId, 100.00);
        $this->createFinancialEntry($tenantId, $clientId, $projectId, $invoiceId, 1000.00);
        $this->createInstallmentPlan($tenantId, $projectId);

        (new ProjectService())->deleteBasic($tenantId, $projectId, 1);

        self::assertNull($this->findProjectRow($projectId));
        self::assertSame(0, $this->countByProject('invoices', $projectId));
        self::assertSame(0, $this->countPaymentsByProject($projectId));
        self::assertSame(0, $this->countByProject('financial_entries', $projectId));
        self::assertSame(0, $this->countByProject('installment_plans', $projectId));
    }

    public function testDeleteBlocksWhenProjectHasSettledFinancialRecords(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente regra');
        $projectId = (new ProjectService())->createBasic($tenantId, $clientId, 1, [
            'name' => 'Projeto bloqueado',
            'description' => '',
            'status' => 'active',
            'start_date' => '',
            'due_date' => '',
            'contract_value' => '1000,00',
            'entry_amount' => '0',
            'payment_terms' => '',
            'installments_count' => 0,
            'first_installment_date' => '',
        ]);

        $this->createInvoice($tenantId, $clientId, $projectId, 'paid', 1000.00);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Não é permitido excluir projeto com valores financeiros já liquidados.');
        (new ProjectService())->deleteBasic($tenantId, $projectId, 1);
    }

    public function testDeleteRollsBackWhenAuditFails(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente rollback');
        $projectName = 'Projeto rollback ' . uniqid('', true);
        $projectId = (new ProjectService())->createBasic($tenantId, $clientId, 1, [
            'name' => $projectName,
            'description' => '',
            'status' => 'active',
            'start_date' => '',
            'due_date' => '',
            'contract_value' => '1000,00',
            'entry_amount' => '0',
            'payment_terms' => '',
            'installments_count' => 0,
            'first_installment_date' => '',
        ]);
        $this->createInvoice($tenantId, $clientId, $projectId, 'pending', 0.00);

        // Sai da transação externa do teste para validar a transação atômica interna do serviço.
        if ($this->pdo instanceof PDO && $this->pdo->inTransaction()) {
            $this->pdo->commit();
        }

        $this->expectException(\Throwable::class);
        try {
            (new ProjectService())->deleteBasic($tenantId, $projectId, 99999999);
        } finally {
            self::assertNotNull($this->findProjectRow($projectId));
            self::assertSame(1, $this->countByProject('invoices', $projectId));
            $this->cleanupProjectFinancialData($projectId);
            if ($this->pdo instanceof PDO && !$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }
        }
    }

    private function createClient(int $tenantId, string $name): int
    {
        $st = $this->pdo->prepare('INSERT INTO clients (tenant_id, name, email, status) VALUES (:tenant_id, :name, :email, :status)');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':email', uniqid('project-crud-', true) . '@example.com', PDO::PARAM_STR);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function fetchProjectName(int $projectId): ?string
    {
        $row = $this->findProjectRow($projectId);
        return is_array($row) ? (string)$row['name'] : null;
    }

    private function fetchProject(int $projectId): array
    {
        $row = $this->findProjectRow($projectId);
        self::assertIsArray($row);
        return $row;
    }

    private function findProjectRow(int $projectId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM projects WHERE id = :id');
        $st->bindValue(':id', $projectId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function createInvoice(int $tenantId, int $clientId, int $projectId, string $status, float $amountPaid): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO invoices (tenant_id, client_id, project_id, code, description, amount_total, amount_paid, status, installment_type, installment_number, due_date)
             VALUES (:tenant_id, :client_id, :project_id, :code, :description, :amount_total, :amount_paid, :status, :installment_type, :installment_number, :due_date)'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->bindValue(':code', uniqid('inv-', true), PDO::PARAM_STR);
        $st->bindValue(':description', 'Parcela teste', PDO::PARAM_STR);
        $st->bindValue(':amount_total', 1000.00);
        $st->bindValue(':amount_paid', $amountPaid);
        $st->bindValue(':status', $status, PDO::PARAM_STR);
        $st->bindValue(':installment_type', 'monthly', PDO::PARAM_STR);
        $st->bindValue(':installment_number', 1, PDO::PARAM_INT);
        $st->bindValue(':due_date', '2026-04-30', PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function createPayment(int $tenantId, int $invoiceId, float $amount): void
    {
        $st = $this->pdo->prepare('INSERT INTO payments (tenant_id, invoice_id, amount, paid_at) VALUES (:tenant_id, :invoice_id, :amount, NOW())');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $st->bindValue(':amount', $amount);
        $st->execute();
    }

    private function createFinancialEntry(int $tenantId, int $clientId, int $projectId, int $invoiceId, float $amount): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO financial_entries (tenant_id, client_id, project_id, invoice_id, type, category, amount, entry_date, status, note)
             VALUES (:tenant_id, :client_id, :project_id, :invoice_id, 'income', 'project_installment', :amount, '2026-04-30', 'pending', 'teste')"
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $st->bindValue(':amount', $amount);
        $st->execute();
    }

    private function createInstallmentPlan(int $tenantId, int $projectId): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO installment_plans (tenant_id, project_id, valor_total, saldo_restante, quantidade_parcelas, valor_parcela)
             VALUES (:tenant_id, :project_id, 1000.00, 1000.00, 1, 1000.00)'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
    }

    private function countByProject(string $table, int $projectId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE project_id = :project_id');
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
        return (int)$st->fetchColumn();
    }

    private function countPaymentsByProject(int $projectId): int
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM payments p
             INNER JOIN invoices i ON i.id = p.invoice_id
             WHERE i.project_id = :project_id'
        );
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
        return (int)$st->fetchColumn();
    }

    private function cleanupProjectFinancialData(int $projectId): void
    {
        $st = $this->pdo->prepare(
            'DELETE FROM financial_entries
             WHERE project_id = :project_id
                OR invoice_id IN (SELECT id FROM invoices WHERE project_id = :project_id)'
        );
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();

        $st = $this->pdo->prepare(
            'DELETE FROM payments
             WHERE invoice_id IN (SELECT id FROM invoices WHERE project_id = :project_id)'
        );
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();

        $st = $this->pdo->prepare('DELETE FROM installment_plans WHERE project_id = :project_id');
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();

        $st = $this->pdo->prepare('DELETE FROM invoices WHERE project_id = :project_id');
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();

        $st = $this->pdo->prepare('DELETE FROM projects WHERE id = :project_id');
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
    }
}
