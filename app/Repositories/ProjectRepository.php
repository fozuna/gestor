<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\DB;
use PDO;

final class ProjectRepository
{
    public function paginatedListByTenant(int $tenantId, string $search, string $status, int $limit, int $offset): array
    {
        $sql = 'SELECT p.id, p.client_id, p.name, p.description, p.status, p.start_date, p.due_date, p.created_at, p.contract_value,
                       c.name AS client_name,
                       COALESCE(tg.tasks_count, 0) AS tasks_count,
                       COALESCE(fg.pending_amount, 0) AS pending_amount,
                       COALESCE(fg.paid_amount, 0) AS paid_amount
                FROM projects p
                INNER JOIN clients c ON c.id = p.client_id
                LEFT JOIN (
                    SELECT project_id, COUNT(*) AS tasks_count
                    FROM tasks
                    WHERE tenant_id = :tasks_tenant_id
                    GROUP BY project_id
                ) tg ON tg.project_id = p.id
                LEFT JOIN (
                    SELECT project_id,
                           SUM(
                               CASE
                                   WHEN status <> "paid" THEN GREATEST(amount_total - LEAST(amount_paid, amount_total), 0)
                                   ELSE 0
                               END
                           ) AS pending_amount,
                           SUM(LEAST(amount_paid, amount_total)) AS paid_amount
                    FROM invoices
                    WHERE tenant_id = :invoices_tenant_id
                    GROUP BY project_id
                ) fg ON fg.project_id = p.id
                WHERE p.tenant_id = :tenant_id';

        if ($search !== '') {
            $sql .= ' AND p.name LIKE :search';
        }

        if ($status !== '') {
            $sql .= ' AND p.status = :status';
        }

        $sql .= ' ORDER BY p.created_at DESC
                  LIMIT :limit OFFSET :offset';

        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tasks_tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':invoices_tenant_id', $tenantId, PDO::PARAM_INT);
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
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function countByTenantFilters(int $tenantId, string $search, string $status): int
    {
        $sql = 'SELECT COUNT(*) FROM projects p WHERE p.tenant_id = :tenant_id';
        if ($search !== '') {
            $sql .= ' AND p.name LIKE :search';
        }
        if ($status !== '') {
            $sql .= ' AND p.status = :status';
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

    public function listByTenant(int $tenantId): array
    {
        $sql = 'SELECT p.id, p.name, p.status, p.due_date, p.created_at, p.contract_value, p.entry_amount, p.installments_count,
                       c.name AS client_name,
                       COALESCE(tg.tasks_count, 0) AS tasks_count,
                       COALESCE(fg.pending_amount, 0) AS pending_amount,
                       COALESCE(fg.paid_amount, 0) AS paid_amount
                FROM projects p
                INNER JOIN clients c ON c.id = p.client_id
                LEFT JOIN (
                    SELECT project_id, COUNT(*) AS tasks_count
                    FROM tasks
                    WHERE tenant_id = :tasks_tenant_id
                    GROUP BY project_id
                ) tg ON tg.project_id = p.id
                LEFT JOIN (
                    SELECT project_id,
                           SUM(
                               CASE
                                   WHEN status <> "paid" THEN GREATEST(amount_total - LEAST(amount_paid, amount_total), 0)
                                   ELSE 0
                               END
                           ) AS pending_amount,
                           SUM(LEAST(amount_paid, amount_total)) AS paid_amount
                    FROM invoices
                    WHERE tenant_id = :invoices_tenant_id
                    GROUP BY project_id
                ) fg ON fg.project_id = p.id
                WHERE p.tenant_id = :tenant_id
                ORDER BY p.created_at DESC';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tasks_tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':invoices_tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function create(int $tenantId, int $clientId, ?int $userId, array $data): int
    {
        $sql = 'INSERT INTO projects (
                    tenant_id, client_id, name, description, status, start_date, due_date,
                    contract_value, payment_terms, entry_amount, installments_count, first_installment_date, created_by_user_id
                ) VALUES (
                    :tenant_id, :client_id, :name, :description, :status, :start_date, :due_date,
                    :contract_value, :payment_terms, :entry_amount, :installments_count, :first_installment_date, :created_by_user_id
                )';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $st->bindValue(':description', $data['description'] !== '' ? $data['description'] : null, $data['description'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':status', $data['status'], PDO::PARAM_STR);
        $st->bindValue(':start_date', $data['start_date'] !== '' ? $data['start_date'] : null, $data['start_date'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':due_date', $data['due_date'] !== '' ? $data['due_date'] : null, $data['due_date'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':contract_value', $data['contract_value']);
        $st->bindValue(':payment_terms', $data['payment_terms'] !== '' ? $data['payment_terms'] : null, $data['payment_terms'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':entry_amount', $data['entry_amount']);
        $st->bindValue(':installments_count', $data['installments_count'], PDO::PARAM_INT);
        $st->bindValue(':first_installment_date', $data['first_installment_date'] !== '' ? $data['first_installment_date'] : null, $data['first_installment_date'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':created_by_user_id', $userId ?: null, $userId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->execute();
        return (int)DB::pdo()->lastInsertId();
    }

    public function update(int $tenantId, int $projectId, int $clientId, array $data): void
    {
        $sql = 'UPDATE projects
                SET client_id = :client_id,
                    name = :name,
                    description = :description,
                    status = :status,
                    start_date = :start_date,
                    due_date = :due_date,
                    contract_value = :contract_value,
                    payment_terms = :payment_terms,
                    entry_amount = :entry_amount,
                    installments_count = :installments_count,
                    first_installment_date = :first_installment_date
                WHERE tenant_id = :tenant_id
                  AND id = :project_id';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $st->bindValue(':description', $data['description'] !== '' ? $data['description'] : null, $data['description'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':status', $data['status'], PDO::PARAM_STR);
        $st->bindValue(':start_date', $data['start_date'] !== '' ? $data['start_date'] : null, $data['start_date'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':due_date', $data['due_date'] !== '' ? $data['due_date'] : null, $data['due_date'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':contract_value', $data['contract_value']);
        $st->bindValue(':payment_terms', $data['payment_terms'] !== '' ? $data['payment_terms'] : null, $data['payment_terms'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':entry_amount', $data['entry_amount']);
        $st->bindValue(':installments_count', $data['installments_count'], PDO::PARAM_INT);
        $st->bindValue(':first_installment_date', $data['first_installment_date'] !== '' ? $data['first_installment_date'] : null, $data['first_installment_date'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
    }

    public function delete(int $tenantId, int $projectId): int
    {
        $st = DB::pdo()->prepare('DELETE FROM projects WHERE tenant_id = :tenant_id AND id = :project_id');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
        return $st->rowCount();
    }

    public function financialDeletionStats(int $tenantId, int $projectId): array
    {
        $pdo = DB::pdo();

        $invoicesSt = $pdo->prepare('SELECT COUNT(*) FROM invoices WHERE tenant_id = :tenant_id AND project_id = :project_id');
        $invoicesSt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $invoicesSt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $invoicesSt->execute();
        $invoices = (int)$invoicesSt->fetchColumn();

        $paymentsSt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM payments p
             INNER JOIN invoices i ON i.id = p.invoice_id
             WHERE p.tenant_id = :tenant_id AND i.project_id = :project_id'
        );
        $paymentsSt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $paymentsSt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $paymentsSt->execute();
        $payments = (int)$paymentsSt->fetchColumn();

        $entriesSt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM financial_entries
             WHERE tenant_id = :tenant_id AND project_id = :project_id'
        );
        $entriesSt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $entriesSt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $entriesSt->execute();
        $financialEntries = (int)$entriesSt->fetchColumn();

        $planSt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM installment_plans
             WHERE tenant_id = :tenant_id AND project_id = :project_id'
        );
        $planSt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $planSt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $planSt->execute();
        $installmentPlans = (int)$planSt->fetchColumn();

        return [
            'invoices' => $invoices,
            'payments' => $payments,
            'financial_entries' => $financialEntries,
            'installment_plans' => $installmentPlans,
        ];
    }

    public function hasSettledFinancialRecords(int $tenantId, int $projectId): bool
    {
        $st = DB::pdo()->prepare(
            'SELECT 1
             FROM invoices i
             WHERE i.tenant_id = :tenant_id
               AND i.project_id = :project_id
               AND (i.status = "paid" OR i.amount_paid > 0)
             LIMIT 1'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchColumn() !== false;
    }

    public function existsForTenant(int $tenantId, string $name): bool
    {
        $sql = 'SELECT id FROM projects WHERE tenant_id = :tenant_id AND name = :name LIMIT 1';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->execute();
        return $st->fetchColumn() !== false;
    }

    public function existsForTenantExcludingId(int $tenantId, string $name, int $projectId): bool
    {
        $sql = 'SELECT id FROM projects WHERE tenant_id = :tenant_id AND name = :name AND id <> :project_id LIMIT 1';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':name', $name, PDO::PARAM_STR);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchColumn() !== false;
    }

    public function optionsByTenant(int $tenantId): array
    {
        $sql = 'SELECT id, name FROM projects WHERE tenant_id = :tenant_id ORDER BY name';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function listByClient(int $tenantId, int $clientId): array
    {
        $sql = 'SELECT p.id, p.name, p.status, p.due_date, p.contract_value,
                       COALESCE(SUM(CASE WHEN i.status <> "paid" THEN i.amount_total - i.amount_paid ELSE 0 END), 0) AS pending_amount,
                       COALESCE(SUM(CASE WHEN i.status = "paid" THEN i.amount_paid ELSE 0 END), 0) AS paid_amount
                FROM projects p
                LEFT JOIN invoices i ON i.project_id = p.id
                WHERE p.tenant_id = :tenant_id AND p.client_id = :client_id
                GROUP BY p.id, p.name, p.status, p.due_date, p.contract_value
                ORDER BY FIELD(p.status, "active", "paused", "done"), p.name';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function findById(int $tenantId, int $projectId): ?array
    {
        $sql = 'SELECT p.id, p.name, p.description, p.status, p.start_date, p.due_date, p.created_at,
                       p.contract_value, p.payment_terms, p.entry_amount, p.installments_count, p.first_installment_date,
                       c.id AS client_id, c.name AS client_name, c.email AS client_email, c.phone AS client_phone,
                       COALESCE(tg.tasks_count, 0) AS tasks_count,
                       COALESCE(fg.installments_total, 0) AS installments_total,
                       COALESCE(fg.pending_amount, 0) AS pending_amount,
                       COALESCE(fg.paid_amount, 0) AS paid_amount
                FROM projects p
                INNER JOIN clients c ON c.id = p.client_id
                LEFT JOIN (
                    SELECT project_id, COUNT(*) AS tasks_count
                    FROM tasks
                    WHERE tenant_id = :tasks_tenant_id
                    GROUP BY project_id
                ) tg ON tg.project_id = p.id
                LEFT JOIN (
                    SELECT project_id,
                           COUNT(*) AS installments_total,
                           SUM(
                               CASE
                                   WHEN status <> "paid" THEN GREATEST(amount_total - LEAST(amount_paid, amount_total), 0)
                                   ELSE 0
                               END
                           ) AS pending_amount,
                           SUM(LEAST(amount_paid, amount_total)) AS paid_amount
                    FROM invoices
                    WHERE tenant_id = :invoices_tenant_id
                    GROUP BY project_id
                ) fg ON fg.project_id = p.id
                WHERE p.tenant_id = :tenant_id
                  AND p.id = :project_id
                LIMIT 1';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tasks_tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':invoices_tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }
}
