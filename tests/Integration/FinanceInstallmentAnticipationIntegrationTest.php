<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\DB;
use App\Services\FinanceService;
use App\Services\MigrationService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

final class FinanceInstallmentAnticipationIntegrationTest extends TestCase
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

    public function testProcessInstallmentAnticipationSettlesInvoicesAndCreatesHistory(): void
    {
        $tenantId = $this->createTenant('tenant-ant');
        $clientId = $this->createClient($tenantId, 'Cliente Antecipacao');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto Antecipacao');
        $invoiceId1 = $this->createInvoice($tenantId, $clientId, $projectId, 'FIN-ANT-001', 300.00, '2099-01-10');
        $invoiceId2 = $this->createInvoice($tenantId, $clientId, $projectId, 'FIN-ANT-002', 400.00, '2099-02-10');

        $result = (new FinanceService())->processInstallmentAnticipation(
            $tenantId,
            $clientId,
            $projectId,
            null,
            [$invoiceId1, $invoiceId2],
            [
                'payment_date' => date('Y-m-d'),
                'payment_method' => 'pix',
                'adjustment_mode' => 'discount',
                'adjustment_rate' => 10.0,
                'note' => 'Quitacao antecipada',
            ]
        );

        self::assertGreaterThan(0, (int)$result['anticipation_id']);
        self::assertSame(2, (int)$result['selected_count']);
        self::assertSame(630.0, round((float)$result['total_amount'], 2));

        $header = $this->findAnticipation((int)$result['anticipation_id']);
        self::assertNotNull($header);
        self::assertSame($clientId, (int)$header['client_id']);
        self::assertSame($projectId, (int)$header['project_id']);
        self::assertSame('discount', (string)$header['adjustment_mode']);
        self::assertSame(630.0, round((float)$header['total_amount'], 2));

        $items = $this->findAnticipationItems((int)$result['anticipation_id']);
        self::assertCount(2, $items);
        self::assertSame('2099-01-10', (string)$items[0]['original_due_date']);
        self::assertSame('2099-02-10', (string)$items[1]['original_due_date']);

        $invoice1 = $this->findInvoice($tenantId, $invoiceId1);
        $invoice2 = $this->findInvoice($tenantId, $invoiceId2);
        self::assertSame('paid', (string)$invoice1['status']);
        self::assertSame('paid', (string)$invoice2['status']);
        self::assertSame('2099-01-10', (string)$invoice1['due_date']);
        self::assertSame('2099-02-10', (string)$invoice2['due_date']);
        self::assertSame(date('Y-m-d'), substr((string)$invoice1['paid_at'], 0, 10));
        self::assertSame(date('Y-m-d'), substr((string)$invoice2['paid_at'], 0, 10));

        $payments = $this->findPaymentsByAnticipation((int)$result['anticipation_id']);
        self::assertCount(2, $payments);
        self::assertSame(630.0, round(array_sum(array_map(static fn(array $row): float => (float)$row['amount'], $payments)), 2));
        self::assertNotEmpty((string)$payments[0]['receipt_path']);
        self::assertNotEmpty((string)$payments[1]['receipt_path']);

        foreach ($payments as $payment) {
            $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string)$payment['receipt_path']);
            $this->generatedFiles[] = $absolutePath;
            self::assertFileExists($absolutePath);
        }
    }

    public function testSimulateInstallmentAnticipationRejectsPastDueInvoice(): void
    {
        $tenantId = $this->createTenant('tenant-ant-fail');
        $clientId = $this->createClient($tenantId, 'Cliente Invalido');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto Invalido');
        $invoiceId = $this->createInvoice($tenantId, $clientId, $projectId, 'FIN-ANT-003', 200.00, '2020-01-10');

        $this->expectException(RuntimeException::class);

        (new FinanceService())->simulateInstallmentAnticipation(
            $tenantId,
            $clientId,
            $projectId,
            [$invoiceId],
            [
                'payment_date' => date('Y-m-d'),
                'payment_method' => 'pix',
                'adjustment_mode' => 'none',
                'adjustment_rate' => 0.0,
                'note' => '',
            ]
        );
    }

    private function createTenant(string $slugPrefix): int
    {
        $slug = $slugPrefix . '-' . uniqid('', true);
        $st = $this->pdo->prepare('INSERT INTO tenants (name, slug) VALUES (:name, :slug)');
        $st->bindValue(':name', 'Tenant Antecipacao', PDO::PARAM_STR);
        $st->bindValue(':slug', $slug, PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function createClient(int $tenantId, string $name): int
    {
        $st = $this->pdo->prepare('INSERT INTO clients (tenant_id, name, email, status) VALUES (:tenant_id, :name, :email, :status)');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':email', uniqid('ant-', true) . '@example.com', PDO::PARAM_STR);
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
        $st->bindValue(':installments_count', 2, PDO::PARAM_INT);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function createInvoice(int $tenantId, int $clientId, int $projectId, string $code, float $amount, string $dueDate): int
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
        $st->bindValue(':amount_total', $amount);
        $st->bindValue(':amount_paid', 0.00);
        $st->bindValue(':status', 'pending', PDO::PARAM_STR);
        $st->bindValue(':installment_type', 'monthly', PDO::PARAM_STR);
        $st->bindValue(':installment_number', 1, PDO::PARAM_INT);
        $st->bindValue(':reference_month', (int)substr($dueDate, 5, 2), PDO::PARAM_INT);
        $st->bindValue(':reference_year', (int)substr($dueDate, 0, 4), PDO::PARAM_INT);
        $st->bindValue(':due_date', $dueDate, PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function findAnticipation(int $anticipationId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM installment_anticipations WHERE id = :id');
        $st->bindValue(':id', $anticipationId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function findAnticipationItems(int $anticipationId): array
    {
        $st = $this->pdo->prepare('SELECT * FROM installment_anticipation_items WHERE anticipation_id = :id ORDER BY id ASC');
        $st->bindValue(':id', $anticipationId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    private function findInvoice(int $tenantId, int $invoiceId): array
    {
        $st = $this->pdo->prepare('SELECT status, due_date, paid_at FROM invoices WHERE tenant_id = :tenant_id AND id = :id');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':id', $invoiceId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        return $row;
    }

    private function findPaymentsByAnticipation(int $anticipationId): array
    {
        $st = $this->pdo->prepare(
            'SELECT p.amount, p.receipt_path
             FROM installment_anticipation_items item
             INNER JOIN payments p ON p.id = item.payment_id
             WHERE item.anticipation_id = :anticipation_id
             ORDER BY item.id ASC'
        );
        $st->bindValue(':anticipation_id', $anticipationId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }
}
