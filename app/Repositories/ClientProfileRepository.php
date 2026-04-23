<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\DB;
use PDO;

final class ClientProfileRepository
{
    public function financialSummary(int $tenantId, int $clientId): array
    {
        $sql = 'SELECT
                    COALESCE(SUM(CASE WHEN i.status <> "paid" THEN 1 ELSE 0 END), 0) AS pending_installments,
                    COALESCE(SUM(CASE WHEN i.status <> "paid" THEN i.amount_total - i.amount_paid ELSE 0 END), 0) AS pending_total,
                    COALESCE(SUM(CASE WHEN i.status <> "paid" AND i.due_date < CURDATE() THEN i.amount_total - i.amount_paid ELSE 0 END), 0) AS overdue_total
                FROM invoices i
                WHERE i.tenant_id = :tenant_id
                  AND i.client_id = :client_id
                  AND NOT (
                    i.project_id IS NULL
                    AND i.installment_type IN ("entry", "monthly")
                    AND i.code LIKE "PRJ-%"
                  )';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return is_array($row) ? $row : [
            'pending_installments' => 0,
            'pending_total' => 0,
            'overdue_total' => 0,
        ];
    }

    public function installmentsHistoryByClient(int $tenantId, int $clientId, string $startDate, string $endDate): array
    {
        $sql = 'SELECT i.id, i.amount_total, i.due_date, i.paid_at,
                       pay.id AS payment_id, pay.receipt_path,
                       CASE WHEN i.status <> "paid" AND i.due_date < CURDATE() THEN "overdue" ELSE i.status END AS status
                FROM invoices i
                LEFT JOIN payments pay ON pay.id = (
                    SELECT p2.id
                    FROM payments p2
                    WHERE p2.tenant_id = i.tenant_id
                      AND p2.invoice_id = i.id
                    ORDER BY p2.id DESC
                    LIMIT 1
                )
                WHERE i.tenant_id = :tenant_id
                  AND i.client_id = :client_id
                  AND NOT (
                    i.project_id IS NULL
                    AND i.installment_type IN ("entry", "monthly")
                    AND i.code LIKE "PRJ-%"
                  )
                  AND i.due_date BETWEEN :start_date AND :end_date
                ORDER BY i.due_date ASC, i.id ASC';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $st->bindValue(':end_date', $endDate, PDO::PARAM_STR);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function installmentsByClient(int $tenantId, int $clientId, string $startDate, string $endDate): array
    {
        $sql = 'SELECT i.id, i.code, i.installment_type, i.installment_number, i.description, i.amount_total, i.amount_paid,
                       CASE WHEN i.status <> "paid" AND i.due_date < CURDATE() THEN "overdue" ELSE i.status END AS status,
                       i.due_date, i.paid_at, i.reference_month, i.reference_year,
                       pay.id AS payment_id, pay.receipt_path,
                       p.name AS project_name, t.title AS task_title
                FROM invoices i
                LEFT JOIN projects p ON p.id = i.project_id
                LEFT JOIN tasks t ON t.id = i.source_task_id
                LEFT JOIN payments pay ON pay.id = (
                    SELECT p2.id
                    FROM payments p2
                    WHERE p2.tenant_id = i.tenant_id
                      AND p2.invoice_id = i.id
                    ORDER BY p2.id DESC
                    LIMIT 1
                )
                WHERE i.tenant_id = :tenant_id
                  AND i.client_id = :client_id
                  AND NOT (
                    i.project_id IS NULL
                    AND i.installment_type IN ("entry", "monthly")
                    AND i.code LIKE "PRJ-%"
                  )
                  AND i.due_date BETWEEN :start_date AND :end_date
                ORDER BY i.due_date ASC, i.id ASC';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        $st->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $st->bindValue(':end_date', $endDate, PDO::PARAM_STR);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
