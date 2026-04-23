<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\DB;
use PDO;

final class BudgetRepository
{
    public function paginatedListByTenant(int $tenantId, string $search, string $status, int $limit, int $offset): array
    {
        $sql = 'SELECT o.id, o.client_id, o.nome_proposta, o.descricao, o.valor_total, o.valor_entrada,
                       o.quantidade_parcelas, o.valor_parcela, o.data_primeira_parcela, o.condicoes_pagamento,
                       o.status, o.data_validade, o.projeto_id, o.created_at,
                       c.name AS client_name, p.name AS project_name
                FROM orcamentos o
                INNER JOIN clients c ON c.id = o.client_id
                LEFT JOIN projects p ON p.id = o.projeto_id
                WHERE o.tenant_id = :tenant_id';

        if ($search !== '') {
            $sql .= ' AND (o.nome_proposta LIKE :search OR c.name LIKE :search)';
        }
        if ($status !== '') {
            $sql .= ' AND o.status = :status';
        }

        $sql .= ' ORDER BY o.created_at DESC, o.id DESC
                  LIMIT :limit OFFSET :offset';

        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($search !== '') {
            $st->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }
        if ($status !== '') {
            $st->bindValue(':status', $status, PDO::PARAM_STR);
        }
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    public function countByTenantFilters(int $tenantId, string $search, string $status): int
    {
        $sql = 'SELECT COUNT(*) FROM orcamentos WHERE tenant_id = :tenant_id';
        if ($search !== '') {
            $sql .= ' AND nome_proposta LIKE :search';
        }
        if ($status !== '') {
            $sql .= ' AND status = :status';
        }
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($search !== '') {
            $st->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        }
        if ($status !== '') {
            $st->bindValue(':status', $status, PDO::PARAM_STR);
        }
        $st->execute();
        return (int)$st->fetchColumn();
    }

    public function findById(int $tenantId, int $budgetId): ?array
    {
        $sql = 'SELECT o.*, c.name AS client_name, c.email AS client_email, c.phone AS client_phone, p.name AS project_name
                FROM orcamentos o
                INNER JOIN clients c ON c.id = o.client_id
                LEFT JOIN projects p ON p.id = o.projeto_id
                WHERE o.tenant_id = :tenant_id AND o.id = :id
                LIMIT 1';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':id', $budgetId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function create(int $tenantId, ?int $userId, array $data): int
    {
        $sql = 'INSERT INTO orcamentos (
                    tenant_id, client_id, nome_proposta, descricao, valor_total, valor_entrada,
                    quantidade_parcelas, valor_parcela, data_primeira_parcela, condicoes_pagamento,
                    status, data_validade, created_by_user_id
                ) VALUES (
                    :tenant_id, :client_id, :nome_proposta, :descricao, :valor_total, :valor_entrada,
                    :quantidade_parcelas, :valor_parcela, :data_primeira_parcela, :condicoes_pagamento,
                    :status, :data_validade, :created_by_user_id
                )';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $data['client_id'], PDO::PARAM_INT);
        $st->bindValue(':nome_proposta', $data['nome_proposta'], PDO::PARAM_STR);
        $st->bindValue(':descricao', $data['descricao'] !== '' ? $data['descricao'] : null, $data['descricao'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':valor_total', $data['valor_total']);
        $st->bindValue(':valor_entrada', $data['valor_entrada'] !== null ? $data['valor_entrada'] : null, $data['valor_entrada'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':quantidade_parcelas', $data['quantidade_parcelas'], PDO::PARAM_INT);
        $st->bindValue(':valor_parcela', $data['valor_parcela']);
        $st->bindValue(':data_primeira_parcela', $data['data_primeira_parcela'] !== '' ? $data['data_primeira_parcela'] : null, $data['data_primeira_parcela'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':condicoes_pagamento', $data['condicoes_pagamento'] !== '' ? $data['condicoes_pagamento'] : null, $data['condicoes_pagamento'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':status', $data['status'], PDO::PARAM_STR);
        $st->bindValue(':data_validade', $data['data_validade'] !== '' ? $data['data_validade'] : null, $data['data_validade'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':created_by_user_id', $userId, $userId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->execute();
        return (int)DB::pdo()->lastInsertId();
    }

    public function update(int $tenantId, int $budgetId, array $data): void
    {
        $sql = 'UPDATE orcamentos
                SET client_id = :client_id,
                    nome_proposta = :nome_proposta,
                    descricao = :descricao,
                    valor_total = :valor_total,
                    valor_entrada = :valor_entrada,
                    quantidade_parcelas = :quantidade_parcelas,
                    valor_parcela = :valor_parcela,
                    data_primeira_parcela = :data_primeira_parcela,
                    condicoes_pagamento = :condicoes_pagamento,
                    status = :status,
                    data_validade = :data_validade
                WHERE tenant_id = :tenant_id AND id = :id';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':client_id', $data['client_id'], PDO::PARAM_INT);
        $st->bindValue(':nome_proposta', $data['nome_proposta'], PDO::PARAM_STR);
        $st->bindValue(':descricao', $data['descricao'] !== '' ? $data['descricao'] : null, $data['descricao'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':valor_total', $data['valor_total']);
        $st->bindValue(':valor_entrada', $data['valor_entrada'] !== null ? $data['valor_entrada'] : null, $data['valor_entrada'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':quantidade_parcelas', $data['quantidade_parcelas'], PDO::PARAM_INT);
        $st->bindValue(':valor_parcela', $data['valor_parcela']);
        $st->bindValue(':data_primeira_parcela', $data['data_primeira_parcela'] !== '' ? $data['data_primeira_parcela'] : null, $data['data_primeira_parcela'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':condicoes_pagamento', $data['condicoes_pagamento'] !== '' ? $data['condicoes_pagamento'] : null, $data['condicoes_pagamento'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':status', $data['status'], PDO::PARAM_STR);
        $st->bindValue(':data_validade', $data['data_validade'] !== '' ? $data['data_validade'] : null, $data['data_validade'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':id', $budgetId, PDO::PARAM_INT);
        $st->execute();
    }

    public function setStatus(int $tenantId, int $budgetId, string $status): void
    {
        $st = DB::pdo()->prepare(
            'UPDATE orcamentos
             SET status = :status
             WHERE tenant_id = :tenant_id AND id = :id'
        );
        $st->bindValue(':status', $status, PDO::PARAM_STR);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':id', $budgetId, PDO::PARAM_INT);
        $st->execute();
    }

    public function setApprovedWithProject(int $tenantId, int $budgetId, int $projectId): void
    {
        $st = DB::pdo()->prepare(
            'UPDATE orcamentos
             SET status = :status, projeto_id = :project_id
             WHERE tenant_id = :tenant_id AND id = :id'
        );
        $st->bindValue(':status', 'aprovado', PDO::PARAM_STR);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':id', $budgetId, PDO::PARAM_INT);
        $st->execute();
    }
}

