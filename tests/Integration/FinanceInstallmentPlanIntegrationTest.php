<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\DB;
use App\Services\FinanceService;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

final class FinanceInstallmentPlanIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = DB::pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS installment_plans');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS installment_plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  valor_total DECIMAL(12,2) NOT NULL,
  valor_entrada DECIMAL(12,2) NULL,
  data_entrada DATE NULL,
  saldo_restante DECIMAL(12,2) NOT NULL,
  quantidade_parcelas INT UNSIGNED NOT NULL DEFAULT 0,
  valor_parcela DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  data_primeira_parcela DATE NULL,
  formas_pagamento TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_installment_plans_tenant_project (tenant_id, project_id),
  KEY idx_installment_plans_tenant_id (tenant_id),
  KEY idx_installment_plans_project_id (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
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

    public function testCreatesPlanWithEntryAndInstallments(): void
    {
        $tenantId = $this->createTenant('Tenant financeiro parcelamento');
        $clientId = $this->createClient($tenantId, 'Cliente financeiro');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto financeiro', 2800.00);

        $planId = (new FinanceService())->createInstallmentPlan($tenantId, $projectId, $clientId, 1, [
            'valor_total' => '2.800,00',
            'valor_entrada' => '1.400,00',
            'data_entrada' => '14/04/2026',
            'data_primeira_parcela' => '15/04/2026',
            'quantidade_parcelas' => 2,
            'formas_pagamento' => 'PIX',
        ]);

        self::assertGreaterThan(0, $planId);
        self::assertNotNull($this->findPlan($tenantId, $projectId));

        $invoices = $this->fetchInvoices($tenantId, $projectId);
        self::assertCount(3, $invoices);
        self::assertSame(2800.0, round(array_sum(array_column($invoices, 'amount_total')), 2));

        $entries = $this->fetchFinancialEntries($tenantId, $projectId);
        self::assertCount(3, $entries);
    }

    public function testCreatesPlanWithoutEntry(): void
    {
        $tenantId = $this->createTenant('Tenant sem entrada');
        $clientId = $this->createClient($tenantId, 'Cliente sem entrada');
        $projectId = $this->createProject($tenantId, $clientId, 'Projeto sem entrada', 1000.00);

        $planId = (new FinanceService())->createInstallmentPlan($tenantId, $projectId, $clientId, 1, [
            'valor_total' => '1.000,00',
            'valor_entrada' => '',
            'data_entrada' => '',
            'data_primeira_parcela' => '01/05/2026',
            'quantidade_parcelas' => 4,
            'formas_pagamento' => 'Boleto',
        ]);

        self::assertGreaterThan(0, $planId);
        $invoices = $this->fetchInvoices($tenantId, $projectId);
        self::assertCount(4, $invoices);
        self::assertSame(1000.0, round(array_sum(array_column($invoices, 'amount_total')), 2));
    }

    private function createTenant(string $name): int
    {
        $st = $this->pdo->prepare('INSERT INTO tenants (name, slug) VALUES (:name, :slug)');
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':slug', uniqid('tenant-fin-', true), PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function createClient(int $tenantId, string $name): int
    {
        $st = $this->pdo->prepare('INSERT INTO clients (tenant_id, name, email, status) VALUES (:tenant_id, :name, :email, :status)');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':email', uniqid('fin-', true) . '@example.com', PDO::PARAM_STR);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function createProject(int $tenantId, int $clientId, string $name, float $contractValue): int
    {
        $st = $this->pdo->prepare('INSERT INTO projects (tenant_id, client_id, name, status, contract_value) VALUES (:tenant_id, :client_id, :name, :status, :contract_value)');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->bindValue(':contract_value', $contractValue);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function findPlan(int $tenantId, int $projectId): ?array
    {
        $st = $this->pdo->prepare('SELECT id FROM installment_plans WHERE tenant_id = :tenant_id AND project_id = :project_id');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function fetchInvoices(int $tenantId, int $projectId): array
    {
        $st = $this->pdo->prepare('SELECT amount_total FROM invoices WHERE tenant_id = :tenant_id AND project_id = :project_id ORDER BY id ASC');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn(array $row): array => ['amount_total' => (float)$row['amount_total']], is_array($rows) ? $rows : []);
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
