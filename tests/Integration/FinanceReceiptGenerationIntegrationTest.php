<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\DB;
use App\Services\FinanceService;
use App\Services\MigrationService;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

final class FinanceReceiptGenerationIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;
    /** @var string[] */
    private array $generatedFiles = [];

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
        foreach ($this->generatedFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    public function testSettleInstallmentGeneratesAndAssociatesPaymentReceiptPdf(): void
    {
        $tenantId = $this->createTenant('tenant-receipt');
        $clientId = $this->createClient($tenantId, 'Cliente Recibo');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto Recibo');
        $invoiceId = $this->createInvoice($tenantId, $clientId, $projectId, 'FIN-RCB-001');

        (new FinanceService())->settleInstallment($tenantId, $invoiceId, null, [
            'amount_paid' => 350.00,
            'payment_date' => '2026-04-10',
            'payment_method' => 'pix',
            'note' => 'Pagamento de teste',
        ]);

        $payment = $this->findLatestPaymentByInvoice($tenantId, $invoiceId);
        self::assertNotNull($payment);
        self::assertMatchesRegularExpression('/^\d{8}$/', (string)$payment['receipt_number']);
        self::assertNotEmpty((string)$payment['receipt_path']);
        self::assertGreaterThan(0, (int)$payment['receipt_size_bytes']);
        self::assertLessThanOrEqual(512000, (int)$payment['receipt_size_bytes']);

        $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string)$payment['receipt_path']);
        $this->generatedFiles[] = $absolutePath;
        self::assertFileExists($absolutePath);
    }

    public function testEnsurePaymentReceiptGeneratesForHistoricalPaidInstallmentWithoutReceipt(): void
    {
        $tenantId = $this->createTenant('tenant-receipt-old');
        $clientId = $this->createClient($tenantId, 'Cliente Histórico');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto Histórico');
        $invoiceId = $this->createInvoice($tenantId, $clientId, $projectId, 'FIN-RCB-002');
        $paymentId = $this->createHistoricalPayment($tenantId, $invoiceId, 350.00);
        $invoiceId2 = $this->createInvoice($tenantId, $clientId, $projectId, 'FIN-RCB-003');
        $paymentId2 = $this->createHistoricalPayment($tenantId, $invoiceId2, 410.00);

        $result = (new FinanceService())->ensurePaymentReceipt($tenantId, $paymentId, null);
        $result2 = (new FinanceService())->ensurePaymentReceipt($tenantId, $paymentId2, null);
        self::assertTrue((bool)$result['generated']);
        self::assertTrue((bool)$result2['generated']);
        self::assertMatchesRegularExpression('/^\d{8}$/', (string)$result['number']);
        self::assertMatchesRegularExpression('/^\d{8}$/', (string)$result2['number']);
        self::assertNotSame((string)$result['number'], (string)$result2['number']);
        self::assertNotEmpty((string)$result['relative_path']);
        self::assertLessThanOrEqual(512000, (int)$result['bytes']);

        $payment = $this->findLatestPaymentByInvoice($tenantId, $invoiceId);
        self::assertNotNull($payment);
        self::assertSame((string)$result['number'], (string)$payment['receipt_number']);

        $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string)$payment['receipt_path']);
        $this->generatedFiles[] = $absolutePath;
        self::assertFileExists($absolutePath);
    }

    private function createTenant(string $slugPrefix): int
    {
        $slug = $slugPrefix . '-' . uniqid('', true);
        $st = $this->pdo->prepare('INSERT INTO tenants (name, slug) VALUES (:name, :slug)');
        $st->bindValue(':name', 'Tenant Recibo', PDO::PARAM_STR);
        $st->bindValue(':slug', $slug, PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function createClient(int $tenantId, string $name): int
    {
        $st = $this->pdo->prepare('INSERT INTO clients (tenant_id, name, email, status) VALUES (:tenant_id, :name, :email, :status)');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':email', uniqid('rcp-', true) . '@example.com', PDO::PARAM_STR);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function createProject(int $tenantId, int $clientId, string $name): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO projects (tenant_id, client_id, name, status, contract_value, entry_amount, installments_count)
             VALUES (:tenant_id, :client_id, :name, :status, :contract_value, :entry_amount, :installments_count)'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->bindValue(':contract_value', 1000.00);
        $st->bindValue(':entry_amount', 0.00);
        $st->bindValue(':installments_count', 1, PDO::PARAM_INT);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function createInvoice(int $tenantId, int $clientId, int $projectId, string $code): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO invoices (
                tenant_id, client_id, project_id, code, description, amount_total, amount_paid, status,
                installment_type, installment_number, reference_month, reference_year, due_date
             ) VALUES (
                :tenant_id, :client_id, :project_id, :code, :description, :amount_total, :amount_paid, :status,
                :installment_type, :installment_number, :reference_month, :reference_year, :due_date
             )'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->bindValue(':code', $code, PDO::PARAM_STR);
        $st->bindValue(':description', 'Parcela mensal', PDO::PARAM_STR);
        $st->bindValue(':amount_total', 350.00);
        $st->bindValue(':amount_paid', 0.00);
        $st->bindValue(':status', 'pending', PDO::PARAM_STR);
        $st->bindValue(':installment_type', 'monthly', PDO::PARAM_STR);
        $st->bindValue(':installment_number', 1, PDO::PARAM_INT);
        $st->bindValue(':reference_month', 4, PDO::PARAM_INT);
        $st->bindValue(':reference_year', 2026, PDO::PARAM_INT);
        $st->bindValue(':due_date', '2026-04-10', PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function findLatestPaymentByInvoice(int $tenantId, int $invoiceId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, receipt_number, receipt_path, receipt_size_bytes
             FROM payments
             WHERE tenant_id = :tenant_id AND invoice_id = :invoice_id
             ORDER BY id DESC
             LIMIT 1'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function createHistoricalPayment(int $tenantId, int $invoiceId, float $amount): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO payments (tenant_id, invoice_id, amount, paid_at, payment_method, received_by_user_id)
             VALUES (:tenant_id, :invoice_id, :amount, :paid_at, :payment_method, :received_by_user_id)'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $st->bindValue(':amount', $amount);
        $st->bindValue(':paid_at', '2026-04-10 12:00:00', PDO::PARAM_STR);
        $st->bindValue(':payment_method', 'pix', PDO::PARAM_STR);
        $st->bindValue(':received_by_user_id', 1, PDO::PARAM_INT);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }
}
