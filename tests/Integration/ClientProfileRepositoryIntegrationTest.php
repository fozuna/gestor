<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\DB;
use App\Repositories\ClientProfileRepository;
use App\Services\ProjectService;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

final class ClientProfileRepositoryIntegrationTest extends TestCase
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

    public function testInstallmentsHistoryByClientReturnsPaidAndPendingInstallmentsInDateRange(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente Historico Completo');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto Historico Completo');
        $this->createInvoice($tenantId, $clientId, $projectId, 'CPR-HST-001', '2026-04-10', 'pending', null);
        $this->createInvoice($tenantId, $clientId, $projectId, 'CPR-HST-002', '2026-04-20', 'paid', '2026-04-22 12:00:00');

        $history = (new ClientProfileRepository())->installmentsHistoryByClient($tenantId, $clientId, '2026-04-01', '2026-04-30');

        self::assertCount(2, $history);
        self::assertSame('2026-04-10', $history[0]['due_date']);
        self::assertNull($history[0]['paid_at']);
        self::assertSame('2026-04-22 12:00:00', $history[1]['paid_at']);
    }

    public function testInstallmentsByClientReturnsAllInstallmentsForSelectedRange(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente Parcelas Range');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto Parcelas Range');
        $this->createInvoice($tenantId, $clientId, $projectId, 'CPR-RNG-001', '2026-05-05', 'pending', null);
        $this->createInvoice($tenantId, $clientId, $projectId, 'CPR-RNG-002', '2026-05-25', 'pending', null);

        $installments = (new ClientProfileRepository())->installmentsByClient($tenantId, $clientId, '2026-05-01', '2026-05-31');

        self::assertCount(2, $installments);
        self::assertSame('CPR-RNG-001', $installments[0]['code']);
        self::assertSame('CPR-RNG-002', $installments[1]['code']);
    }

    public function testFinancialCardsAreUpdatedAfterProjectDeletionCascade(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente Exclusao Projeto');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto Exclusao Perfil');

        $this->createInvoice($tenantId, $clientId, $projectId, 'PRJ-DEL-001', '2026-06-15', 'pending', null);
        (new ProjectService())->deleteBasic($tenantId, $projectId, 1);

        $repo = new ClientProfileRepository();
        $summary = $repo->financialSummary($tenantId, $clientId);
        $history = $repo->installmentsHistoryByClient($tenantId, $clientId, '2026-06-01', '2026-06-30');
        $installments = $repo->installmentsByClient($tenantId, $clientId, '2026-06-01', '2026-06-30');

        self::assertSame('0', (string)$summary['pending_installments']);
        self::assertSame('0.00', number_format((float)$summary['pending_total'], 2, '.', ''));
        self::assertCount(0, $history);
        self::assertCount(0, $installments);
    }

    private function createClient(int $tenantId, string $name): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO clients (tenant_id, name, email, status) VALUES (:tenant_id, :name, :email, :status)'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':email', uniqid('client-profile-', true) . '@example.com', PDO::PARAM_STR);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->execute();

        return (int)$this->pdo->lastInsertId();
    }

    private function createProject(int $tenantId, int $clientId, string $name): int
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
        $st->bindValue(':contract_value', 1200.00);
        $st->bindValue(':entry_amount', 0.00);
        $st->bindValue(':installments_count', 2, PDO::PARAM_INT);
        $st->bindValue(':created_by_user_id', 1, PDO::PARAM_INT);
        $st->execute();

        return (int)$this->pdo->lastInsertId();
    }

    private function createInvoice(
        int $tenantId,
        int $clientId,
        int $projectId,
        string $code,
        string $dueDate,
        string $status,
        ?string $paidAt
    ): void {
        $st = $this->pdo->prepare(
            'INSERT INTO invoices (
                tenant_id, client_id, project_id, code, description, amount_total, amount_paid, status,
                installment_type, installment_number, reference_month, reference_year, due_date, paid_at, created_by_user_id
            ) VALUES (
                :tenant_id, :client_id, :project_id, :code, :description, :amount_total, :amount_paid, :status,
                :installment_type, :installment_number, :reference_month, :reference_year, :due_date, :paid_at, :created_by_user_id
            )'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->bindValue(':code', $code, PDO::PARAM_STR);
        $st->bindValue(':description', 'Parcela do perfil', PDO::PARAM_STR);
        $st->bindValue(':amount_total', 600.00);
        $st->bindValue(':amount_paid', $status === 'paid' ? 600.00 : 0.00);
        $st->bindValue(':status', $status, PDO::PARAM_STR);
        $st->bindValue(':installment_type', 'monthly', PDO::PARAM_STR);
        $st->bindValue(':installment_number', 1, PDO::PARAM_INT);
        $st->bindValue(':reference_month', (int)substr($dueDate, 5, 2), PDO::PARAM_INT);
        $st->bindValue(':reference_year', (int)substr($dueDate, 0, 4), PDO::PARAM_INT);
        $st->bindValue(':due_date', $dueDate, PDO::PARAM_STR);
        $st->bindValue(':paid_at', $paidAt, $paidAt !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':created_by_user_id', 1, PDO::PARAM_INT);
        $st->execute();
    }

}
