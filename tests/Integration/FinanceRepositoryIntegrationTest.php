<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\DB;
use App\Repositories\FinanceRepository;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

final class FinanceRepositoryIntegrationTest extends TestCase
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

    public function testListByTenantWithPeriodLoadsFinancialRowsWithoutInvalidParameterError(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente Financeiro Periodo');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto Financeiro Periodo');
        $invoiceId = $this->createInvoice($tenantId, $clientId, $projectId, 'INT-FIN-001', 4, 2026);
        $this->createPayment($tenantId, $invoiceId, 250.00);

        $rows = (new FinanceRepository())->listByTenant($tenantId, 4, 2026);

        self::assertNotEmpty($rows);
        self::assertContains('INT-FIN-001', array_column($rows, 'code'));
    }

    public function testListByTenantWithoutPeriodAlsoLoadsFinancialRows(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente Financeiro Geral');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto Financeiro Geral');
        $invoiceId = $this->createInvoice($tenantId, $clientId, $projectId, 'INT-FIN-002', 5, 2026);
        $this->createPayment($tenantId, $invoiceId, 300.00);

        $rows = (new FinanceRepository())->listByTenant($tenantId);

        self::assertNotEmpty($rows);
        self::assertContains('INT-FIN-002', array_column($rows, 'code'));
    }

    public function testFindPaymentByIdReturnsReceiptMetadata(): void
    {
        $tenantId = 1;
        $clientId = $this->createClient($tenantId, 'Cliente Financeiro Recibo');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto Financeiro Recibo');
        $invoiceId = $this->createInvoice($tenantId, $clientId, $projectId, 'INT-FIN-003', 6, 2026);
        $paymentId = $this->createPayment($tenantId, $invoiceId, 320.00, 'RCB-0001-00000001', 'storage/recibos/20260410/tenant-1/recibo-1.pdf');

        $payment = (new FinanceRepository())->findPaymentById($tenantId, $paymentId);

        self::assertNotNull($payment);
        self::assertSame('RCB-0001-00000001', (string)$payment['receipt_number']);
        self::assertSame('storage/recibos/20260410/tenant-1/recibo-1.pdf', (string)$payment['receipt_path']);
    }

    private function createClient(int $tenantId, string $name): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO clients (tenant_id, name, email, status) VALUES (:tenant_id, :name, :email, :status)'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':email', uniqid('finance-', true) . '@example.com', PDO::PARAM_STR);
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
        $st->bindValue(':contract_value', 1000.00);
        $st->bindValue(':entry_amount', 0.00);
        $st->bindValue(':installments_count', 1, PDO::PARAM_INT);
        $st->bindValue(':created_by_user_id', 1, PDO::PARAM_INT);
        $st->execute();

        return (int)$this->pdo->lastInsertId();
    }

    private function createInvoice(int $tenantId, int $clientId, int $projectId, string $code, int $month, int $year): int
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
        $st->bindValue(':description', 'Parcela de teste', PDO::PARAM_STR);
        $st->bindValue(':amount_total', 500.00);
        $st->bindValue(':amount_paid', 0.00);
        $st->bindValue(':status', 'pending', PDO::PARAM_STR);
        $st->bindValue(':installment_type', 'monthly', PDO::PARAM_STR);
        $st->bindValue(':installment_number', 1, PDO::PARAM_INT);
        $st->bindValue(':reference_month', $month, PDO::PARAM_INT);
        $st->bindValue(':reference_year', $year, PDO::PARAM_INT);
        $st->bindValue(':due_date', sprintf('%04d-%02d-15', $year, $month), PDO::PARAM_STR);
        $st->bindValue(':created_by_user_id', 1, PDO::PARAM_INT);
        $st->execute();

        return (int)$this->pdo->lastInsertId();
    }

    private function createPayment(
        int $tenantId,
        int $invoiceId,
        float $amount,
        ?string $receiptNumber = null,
        ?string $receiptPath = null
    ): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO payments (
                tenant_id, invoice_id, amount, paid_at, payment_method, received_by_user_id,
                receipt_number, receipt_path, receipt_generated_at, receipt_size_bytes
             ) VALUES (
                :tenant_id, :invoice_id, :amount, :paid_at, :payment_method, :received_by_user_id,
                :receipt_number, :receipt_path, :receipt_generated_at, :receipt_size_bytes
             )'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $st->bindValue(':amount', $amount);
        $st->bindValue(':paid_at', '2026-04-10 12:00:00', PDO::PARAM_STR);
        $st->bindValue(':payment_method', 'pix', PDO::PARAM_STR);
        $st->bindValue(':received_by_user_id', 1, PDO::PARAM_INT);
        $st->bindValue(':receipt_number', $receiptNumber, $receiptNumber !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':receipt_path', $receiptPath, $receiptPath !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':receipt_generated_at', $receiptPath !== null ? '2026-04-10 12:05:00' : null, $receiptPath !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':receipt_size_bytes', $receiptPath !== null ? 20480 : null, $receiptPath !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }
}
