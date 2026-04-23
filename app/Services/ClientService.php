<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Security;
use App\Repositories\ClientRepository;
use RuntimeException;

final class ClientService
{
    public function __construct(private readonly ClientRepository $clients = new ClientRepository())
    {
    }

    public function listByTenant(int $tenantId): array
    {
        return $this->clients->listByTenant($tenantId);
    }

    public function optionsByTenant(int $tenantId): array
    {
        return $this->clients->optionsByTenant($tenantId);
    }

    public function findById(int $tenantId, int $clientId): ?array
    {
        return $this->clients->findById($tenantId, $clientId);
    }

    public function create(int $tenantId, array $data): int
    {
        $name = trim((string)($data['name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $status = (string)($data['status'] ?? 'active');

        if ($name === '') {
            throw new RuntimeException('Informe o nome do cliente.');
        }

        if ($email !== '' && !Security::isValidEmail($email)) {
            throw new RuntimeException('Informe um e-mail válido para o cliente.');
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Status de cliente inválido.');
        }

        if ($this->clients->existsForTenant($tenantId, $name)) {
            throw new RuntimeException('Já existe um cliente com esse nome neste workspace.');
        }

        return $this->clients->create($tenantId, [
            'name' => $name,
            'email' => $email,
            'phone' => trim((string)($data['phone'] ?? '')),
            'status' => $status,
            'notes' => trim((string)($data['notes'] ?? '')),
        ]);
    }

    public function update(int $tenantId, int $clientId, array $data): void
    {
        $current = $this->clients->findById($tenantId, $clientId);
        if ($current === null) {
            throw new RuntimeException('Cliente não encontrado.');
        }

        $name = trim((string)($data['name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $status = (string)($data['status'] ?? 'active');
        if ($name === '') {
            throw new RuntimeException('Informe o nome do cliente.');
        }
        if ($email !== '' && !Security::isValidEmail($email)) {
            throw new RuntimeException('Informe um e-mail válido para o cliente.');
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Status de cliente inválido.');
        }

        $this->clients->update($tenantId, $clientId, [
            'name' => $name,
            'email' => $email,
            'phone' => trim((string)($data['phone'] ?? '')),
            'status' => $status,
            'notes' => trim((string)($data['notes'] ?? '')),
        ]);
    }

    public function delete(int $tenantId, int $clientId): void
    {
        $current = $this->clients->findById($tenantId, $clientId);
        if ($current === null) {
            throw new RuntimeException('Cliente não encontrado.');
        }

        if ($this->clients->hasActiveProjects($tenantId, $clientId)) {
            throw new RuntimeException('Não é possível excluir este cliente pois existem projetos ativos vinculados.');
        }

        $deleted = $this->clients->delete($tenantId, $clientId);
        if ($deleted !== 1) {
            throw new RuntimeException('Falha ao excluir o cliente.');
        }
    }
}
