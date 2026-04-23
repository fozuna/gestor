<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\DB;
use App\Services\ClientService;
use App\Services\MigrationService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

final class ClientMaintenanceIntegrationTest extends TestCase
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

    public function testDeleteIsAllowedWhenClientHasNoActiveProjects(): void
    {
        $tenantId = $this->createTenant('tenant-client-del-ok');
        $clientId = $this->createClient($tenantId, 'Cliente sem projeto ativo');

        (new ClientService())->delete($tenantId, $clientId);
        self::assertNull($this->findClient($tenantId, $clientId));
    }

    public function testDeleteIsBlockedWhenClientHasActiveProject(): void
    {
        $tenantId = $this->createTenant('tenant-client-del-block');
        $clientId = $this->createClient($tenantId, 'Cliente com projeto ativo');
        $this->createProject($tenantId, $clientId, 'Projeto ativo', 'active');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Não é possível excluir este cliente pois existem projetos ativos vinculados.');
        (new ClientService())->delete($tenantId, $clientId);
    }

    public function testUpdateClientEditsDataCorrectly(): void
    {
        $tenantId = $this->createTenant('tenant-client-edit');
        $clientId = $this->createClient($tenantId, 'Cliente Original');

        (new ClientService())->update($tenantId, $clientId, [
            'name' => 'Cliente Atualizado',
            'email' => 'novo.email@example.com',
            'phone' => '11999990000',
            'status' => 'inactive',
            'notes' => 'Notas novas',
        ]);

        $client = $this->findClient($tenantId, $clientId);
        self::assertNotNull($client);
        self::assertSame('Cliente Atualizado', (string)$client['name']);
        self::assertSame('novo.email@example.com', (string)$client['email']);
        self::assertSame('inactive', (string)$client['status']);
    }

    private function createTenant(string $slug): int
    {
        $st = $this->pdo->prepare('INSERT INTO tenants (name, slug) VALUES (:name, :slug)');
        $st->bindValue(':name', 'Tenant ' . $slug, PDO::PARAM_STR);
        $st->bindValue(':slug', $slug . '-' . uniqid('', true), PDO::PARAM_STR);
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
        $st->bindValue(':email', uniqid('client-maint-', true) . '@example.com', PDO::PARAM_STR);
        $st->bindValue(':status', 'active', PDO::PARAM_STR);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function createProject(int $tenantId, int $clientId, string $name, string $status): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO projects (tenant_id, client_id, name, status, contract_value, entry_amount, installments_count)
             VALUES (:tenant_id, :client_id, :name, :status, :contract_value, :entry_amount, :installments_count)'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':status', $status, PDO::PARAM_STR);
        $st->bindValue(':contract_value', 1200.00);
        $st->bindValue(':entry_amount', 0.00);
        $st->bindValue(':installments_count', 1, PDO::PARAM_INT);
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function findClient(int $tenantId, int $clientId): ?array
    {
        $st = $this->pdo->prepare('SELECT id, name, email, status FROM clients WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':id', $clientId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}

