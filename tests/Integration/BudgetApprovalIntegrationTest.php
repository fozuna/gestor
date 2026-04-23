<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\DB;
use App\Services\BudgetService;
use App\Services\MigrationService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

final class BudgetApprovalIntegrationTest extends TestCase
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

    public function testCreateAndApproveBudgetCreatesProjectOnce(): void
    {
        $tenantId = $this->createTenant('Tenant orçamento');
        $clientId = $this->createClient($tenantId, 'Cliente orçamento');

        $service = new BudgetService();
        $budgetId = $service->create($tenantId, null, [
            'client_id' => $clientId,
            'nome_proposta' => 'Proposta App Web',
            'descricao' => 'Escopo completo do app',
            'valor_total' => '12.000,00',
            'valor_entrada' => '2.000,00',
            'quantidade_parcelas' => 5,
            'data_primeira_parcela' => '10/05/2026',
            'condicoes_pagamento' => 'PIX mensal',
            'status' => 'rascunho',
            'data_validade' => '30/04/2026',
        ]);

        self::assertGreaterThan(0, $budgetId);
        $service->changeStatus($tenantId, $budgetId, 'aprovado', null);

        $budget = $service->detail($tenantId, $budgetId);
        self::assertNotNull($budget);
        self::assertSame('aprovado', (string)$budget['status']);
        self::assertGreaterThan(0, (int)$budget['projeto_id']);

        $project = $this->findProject($tenantId, (int)$budget['projeto_id']);
        self::assertNotNull($project);
        self::assertSame('Proposta App Web', (string)$project['name']);

        $this->expectException(RuntimeException::class);
        $service->changeStatus($tenantId, $budgetId, 'aprovado', null);
    }

    private function createTenant(string $name): int
    {
        $st = $this->pdo->prepare('INSERT INTO tenants (name, slug) VALUES (:name, :slug)');
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':slug', uniqid('tenant-budget-', true), PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function createClient(int $tenantId, string $name): int
    {
        $st = $this->pdo->prepare('INSERT INTO clients (tenant_id, name, email, status) VALUES (:tenant_id, :name, :email, :status)');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':email', uniqid('budget-client-', true) . '@example.com', PDO::PARAM_STR);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function findProject(int $tenantId, int $projectId): ?array
    {
        $st = $this->pdo->prepare('SELECT id, name FROM projects WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':id', $projectId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}

