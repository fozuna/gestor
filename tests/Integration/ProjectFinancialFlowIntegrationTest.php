<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\DB;
use App\Services\ProjectService;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

final class ProjectFinancialFlowIntegrationTest extends TestCase
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

    public function testCreateGeneratesInvoicesAndCashflowWithAutoSuggestedInstallments(): void
    {
        $tenantId = $this->createTenant('Tenant financeiro projetos');
        $clientId = $this->createClient($tenantId, 'Cliente financeiro');

        $projectId = (new ProjectService())->create($tenantId, $clientId, null, [
            'name' => 'Projeto Financeiro',
            'description' => '',
            'status' => 'active',
            'start_date' => '10/12/2025',
            'due_date' => '30/11/2026',
            'contract_value' => '8.000,00',
            'entry_amount' => '2.000,00',
            'payment_terms' => 'Pix mensal',
            'installments_count' => 12,
            'first_installment_date' => '14/12/2025',
        ]);

        self::assertGreaterThan(0, $projectId);

        $invoices = $this->fetchInvoices($tenantId, $projectId);
        self::assertCount(13, $invoices);

        $total = array_reduce($invoices, static fn(float $carry, array $row): float => $carry + (float)$row['amount_total'], 0.0);
        self::assertSame(8000.0, round($total, 2));

        $entry = array_values(array_filter($invoices, static fn(array $row): bool => (string)$row['installment_type'] === 'entry'));
        self::assertCount(1, $entry);
        self::assertSame(2000.0, (float)$entry[0]['amount_total']);

        $entries = $this->fetchFinancialEntries($tenantId, $projectId);
        self::assertCount(13, $entries);
    }

    public function testEnsureFinancialPlanCreatesPlanOnceForExistingProjectWithoutInvoices(): void
    {
        $tenantId = $this->createTenant('Tenant ensure plan');
        $clientId = $this->createClient($tenantId, 'Cliente ensure');

        $service = new ProjectService();
        $projectId = $service->createBasic($tenantId, $clientId, null, [
            'name' => 'Projeto sem financeiro',
            'description' => '',
            'status' => 'active',
            'start_date' => '10/12/2025',
            'due_date' => '30/11/2026',
            'contract_value' => '8.000,00',
            'entry_amount' => '2.000,00',
            'payment_terms' => 'Pix mensal',
            'installments_count' => 12,
            'first_installment_date' => '14/12/2025',
        ]);

        self::assertCount(0, $this->fetchInvoices($tenantId, $projectId));

        $created = $service->ensureFinancialPlan($tenantId, $projectId, $clientId, null, [
            'contract_value' => '8.000,00',
            'entry_amount' => '2.000,00',
            'payment_terms' => 'Pix mensal',
            'installments_count' => 12,
            'first_installment_date' => '14/12/2025',
        ]);
        self::assertTrue($created);
        self::assertCount(13, $this->fetchInvoices($tenantId, $projectId));

        $createdAgain = $service->ensureFinancialPlan($tenantId, $projectId, $clientId, null, [
            'contract_value' => '8.000,00',
            'entry_amount' => '2.000,00',
            'payment_terms' => 'Pix mensal',
            'installments_count' => 12,
            'first_installment_date' => '14/12/2025',
        ]);
        self::assertFalse($createdAgain);
    }

    private function createTenant(string $name): int
    {
        $st = $this->pdo->prepare('INSERT INTO tenants (name, slug) VALUES (:name, :slug)');
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':slug', uniqid('tenant-project-fin-', true), PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function createClient(int $tenantId, string $name): int
    {
        $st = $this->pdo->prepare('INSERT INTO clients (tenant_id, name, email, status) VALUES (:tenant_id, :name, :email, :status)');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':email', uniqid('client-fin-', true) . '@example.com', PDO::PARAM_STR);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function fetchInvoices(int $tenantId, int $projectId): array
    {
        $st = $this->pdo->prepare('SELECT amount_total, installment_type FROM invoices WHERE tenant_id = :tenant_id AND project_id = :project_id ORDER BY due_date ASC, id ASC');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    private function fetchFinancialEntries(int $tenantId, int $projectId): array
    {
        $st = $this->pdo->prepare('SELECT id FROM financial_entries WHERE tenant_id = :tenant_id AND project_id = :project_id ORDER BY id ASC');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }
}

