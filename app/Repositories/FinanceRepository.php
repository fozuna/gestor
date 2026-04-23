<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\DB;
use RuntimeException;
use PDO;

final class FinanceRepository
{
    public function summary(int $tenantId, ?int $month = null, ?int $year = null): array
    {
        $pdo = DB::pdo();
        $incomeSql = 'SELECT COALESCE(SUM(amount_total), 0) AS total
                      FROM invoices
                      WHERE tenant_id = :tenant_id';

        if ($month !== null && $year !== null) {
            $incomeSql .= ' AND reference_month = :month AND reference_year = :year';
        }

        $income = $pdo->prepare($incomeSql);
        $income->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($month !== null && $year !== null) {
            $income->bindValue(':month', $month, PDO::PARAM_INT);
            $income->bindValue(':year', $year, PDO::PARAM_INT);
        }
        $income->execute();

        $incomeTotal = (float)($income->fetch()['total'] ?? 0);
        $paid = $this->paidAmountByPeriod($tenantId, $month, $year);
        $pending = $this->pendingAmountByPeriod($tenantId, $month, $year);
        $overdue = $this->overdueAmountByPeriod($tenantId, $month, $year);

        return [
            'income' => $incomeTotal,
            'balance' => $incomeTotal,
            'paid' => $paid,
            'pending' => $pending,
            'overdue' => $overdue,
        ];
    }

    public function listByTenant(int $tenantId, ?int $month = null, ?int $year = null): array
    {
        $sql = 'SELECT i.id, i.code, i.description, i.amount_total, i.amount_paid,
                       CASE WHEN i.status <> "paid" AND i.due_date < CURDATE() THEN "overdue" ELSE i.status END AS status,
                       i.installment_type, i.installment_number,
                       i.reference_month, i.reference_year, i.due_date, i.paid_at, i.payment_terms,
                       c.name AS client_name, p.name AS project_name, t.title AS task_title,
                       pay.id AS last_payment_id, pay.amount AS last_payment_amount, pay.paid_at AS last_payment_date,
                       pay.receipt_number AS last_receipt_number, pay.receipt_path AS last_receipt_path
                FROM invoices i
                INNER JOIN clients c ON c.id = i.client_id
                LEFT JOIN projects p ON p.id = i.project_id
                LEFT JOIN tasks t ON t.id = i.source_task_id
                LEFT JOIN (
                    SELECT invoice_id, MAX(id) AS payment_id
                    FROM payments
                    WHERE tenant_id = :payments_tenant_id
                    GROUP BY invoice_id
                ) latest ON latest.invoice_id = i.id
                LEFT JOIN payments pay ON pay.id = latest.payment_id
                WHERE i.tenant_id = :tenant_id';

        if ($month !== null && $year !== null) {
            $sql .= ' AND i.reference_month = :month AND i.reference_year = :year';
        }

        $sql .= ' ORDER BY i.reference_year DESC, i.reference_month DESC, i.due_date ASC, i.id ASC';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':payments_tenant_id', $tenantId, PDO::PARAM_INT);
        if ($month !== null && $year !== null) {
            $st->bindValue(':month', $month, PDO::PARAM_INT);
            $st->bindValue(':year', $year, PDO::PARAM_INT);
        }
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function findPaymentById(int $tenantId, int $paymentId): ?array
    {
        $st = DB::pdo()->prepare(
            'SELECT id, tenant_id, invoice_id, amount, paid_at, receipt_number, receipt_path, receipt_size_bytes
             FROM payments
             WHERE tenant_id = :tenant_id AND id = :payment_id
             LIMIT 1'
        );
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function createInvoice(array $data): int
    {
        $sql = 'INSERT INTO invoices (
                    tenant_id, client_id, project_id, code, description, amount_total, amount_paid, status,
                    installment_type, installment_number, reference_month, reference_year, source_task_id,
                    payment_terms, due_date, created_by_user_id
                ) VALUES (
                    :tenant_id, :client_id, :project_id, :code, :description, :amount_total, 0, :status,
                    :installment_type, :installment_number, :reference_month, :reference_year, :source_task_id,
                    :payment_terms, :due_date, :created_by_user_id
                )';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
        $st->bindValue(':client_id', $data['client_id'], PDO::PARAM_INT);
        $st->bindValue(':project_id', $data['project_id'], PDO::PARAM_INT);
        $st->bindValue(':code', $data['code'], PDO::PARAM_STR);
        $st->bindValue(':description', $data['description'], PDO::PARAM_STR);
        $st->bindValue(':amount_total', $data['amount_total']);
        $st->bindValue(':status', $data['status'], PDO::PARAM_STR);
        $st->bindValue(':installment_type', $data['installment_type'], PDO::PARAM_STR);
        $st->bindValue(':installment_number', $data['installment_number'], PDO::PARAM_INT);
        $st->bindValue(':reference_month', $data['reference_month'], PDO::PARAM_INT);
        $st->bindValue(':reference_year', $data['reference_year'], PDO::PARAM_INT);
        $st->bindValue(':source_task_id', $data['source_task_id'], $data['source_task_id'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':payment_terms', $data['payment_terms'] !== '' ? $data['payment_terms'] : null, $data['payment_terms'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':due_date', $data['due_date'], PDO::PARAM_STR);
        $st->bindValue(':created_by_user_id', $data['created_by_user_id'] ?? null, ($data['created_by_user_id'] ?? null) !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->execute();
        return (int)DB::pdo()->lastInsertId();
    }

    public function createCashFlowEntry(array $data): int
    {
        $sql = 'INSERT INTO financial_entries (
                    tenant_id, client_id, project_id, invoice_id, type, category, amount, entry_date, status, note, created_by_user_id
                ) VALUES (
                    :tenant_id, :client_id, :project_id, :invoice_id, :type, :category, :amount, :entry_date, :status, :note, :created_by_user_id
                )';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
        $st->bindValue(':client_id', $data['client_id'], PDO::PARAM_INT);
        $st->bindValue(':project_id', $data['project_id'], PDO::PARAM_INT);
        $st->bindValue(':invoice_id', $data['invoice_id'], PDO::PARAM_INT);
        $st->bindValue(':type', $data['type'], PDO::PARAM_STR);
        $st->bindValue(':category', $data['category'], PDO::PARAM_STR);
        $st->bindValue(':amount', $data['amount']);
        $st->bindValue(':entry_date', $data['entry_date'], PDO::PARAM_STR);
        $st->bindValue(':status', $data['status'], PDO::PARAM_STR);
        $st->bindValue(':note', $data['note'], PDO::PARAM_STR);
        $st->bindValue(':created_by_user_id', $data['created_by_user_id'] ?? null, ($data['created_by_user_id'] ?? null) !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->execute();
        return (int)DB::pdo()->lastInsertId();
    }

    public function findInvoiceById(int $tenantId, int $invoiceId): ?array
    {
        $sql = 'SELECT i.*,
                       CASE WHEN i.status <> "paid" AND i.due_date < CURDATE() THEN "overdue" ELSE i.status END AS computed_status,
                       c.name AS client_name, p.name AS project_name
                FROM invoices i
                INNER JOIN clients c ON c.id = i.client_id
                LEFT JOIN projects p ON p.id = i.project_id
                WHERE i.tenant_id = :tenant_id AND i.id = :invoice_id
                LIMIT 1';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function findPaymentByInvoice(int $tenantId, int $invoiceId): ?array
    {
        $sql = 'SELECT id, amount, paid_at, payment_method, note, receipt_number, receipt_path, receipt_generated_at, receipt_size_bytes
                FROM payments
                WHERE tenant_id = :tenant_id AND invoice_id = :invoice_id
                ORDER BY id DESC
                LIMIT 1';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function registerPayment(array $data): int
    {
        $sql = 'INSERT INTO payments (tenant_id, invoice_id, amount, paid_at, payment_method, note, received_by_user_id)
                VALUES (:tenant_id, :invoice_id, :amount, :paid_at, :payment_method, :note, :received_by_user_id)';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
        $st->bindValue(':invoice_id', $data['invoice_id'], PDO::PARAM_INT);
        $st->bindValue(':amount', $data['amount']);
        $st->bindValue(':paid_at', $data['paid_at'], PDO::PARAM_STR);
        $st->bindValue(':payment_method', $data['payment_method'] !== '' ? $data['payment_method'] : null, $data['payment_method'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':note', $data['note'] !== '' ? $data['note'] : null, $data['note'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':received_by_user_id', $data['received_by_user_id'] ?? null, ($data['received_by_user_id'] ?? null) !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->execute();
        return (int)DB::pdo()->lastInsertId();
    }

    public function attachPaymentReceipt(
        int $tenantId,
        int $paymentId,
        string $receiptNumber,
        string $receiptPath,
        int $sizeBytes
    ): void {
        $sql = 'UPDATE payments
                SET receipt_number = :receipt_number,
                    receipt_path = :receipt_path,
                    receipt_generated_at = :receipt_generated_at,
                    receipt_size_bytes = :receipt_size_bytes
                WHERE tenant_id = :tenant_id AND id = :payment_id';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':receipt_number', $receiptNumber, PDO::PARAM_STR);
        $st->bindValue(':receipt_path', $receiptPath, PDO::PARAM_STR);
        $st->bindValue(':receipt_generated_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $st->bindValue(':receipt_size_bytes', $sizeBytes, PDO::PARAM_INT);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
        $st->execute();
    }

    public function findPaymentReceiptContext(int $tenantId, int $paymentId): ?array
    {
        $sql = 'SELECT pay.id AS payment_id, pay.amount AS amount_paid, pay.paid_at, pay.payment_method, pay.note,
                       i.id AS invoice_id, i.code AS invoice_code, i.description AS invoice_description, i.installment_number, i.due_date,
                       c.id AS client_id, c.name AS client_name, c.legal_name AS client_legal_name, c.document_number AS client_document, c.email AS client_email,
                       p.id AS project_id, p.name AS project_name, p.description AS project_description,
                       t.id AS tenant_id, t.name AS tenant_name
                FROM payments pay
                INNER JOIN invoices i ON i.id = pay.invoice_id
                INNER JOIN clients c ON c.id = i.client_id
                LEFT JOIN projects p ON p.id = i.project_id
                INNER JOIN tenants t ON t.id = pay.tenant_id
                WHERE pay.tenant_id = :tenant_id
                  AND pay.id = :payment_id
                LIMIT 1';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function nextGlobalReceiptSequence(): int
    {
        $pdo = DB::pdo();
        $scope = 'global';

        $seed = $pdo->prepare(
            'INSERT INTO receipt_sequences (scope_key, current_value)
             SELECT :scope_key, 0
             WHERE NOT EXISTS (SELECT 1 FROM receipt_sequences WHERE scope_key = :scope_key_check)'
        );
        $seed->bindValue(':scope_key', $scope, PDO::PARAM_STR);
        $seed->bindValue(':scope_key_check', $scope, PDO::PARAM_STR);
        $seed->execute();

        $lock = $pdo->prepare(
            'SELECT id, current_value
             FROM receipt_sequences
             WHERE scope_key = :scope_key
             LIMIT 1
             FOR UPDATE'
        );
        $lock->bindValue(':scope_key', $scope, PDO::PARAM_STR);
        $lock->execute();
        $row = $lock->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Falha ao obter sequência global de recibo.');
        }

        $next = ((int)$row['current_value']) + 1;
        $up = $pdo->prepare('UPDATE receipt_sequences SET current_value = :current_value WHERE id = :id');
        $up->bindValue(':current_value', $next, PDO::PARAM_INT);
        $up->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
        $up->execute();

        return $next;
    }

    public function settleInvoice(int $tenantId, int $invoiceId, float $totalAmountPaid, string $paidAt, string $status): void
    {
        $sql = 'UPDATE invoices
                SET amount_paid = :amount_paid, paid_at = :paid_at, status = :status
                WHERE tenant_id = :tenant_id AND id = :invoice_id';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':amount_paid', $totalAmountPaid);
        $st->bindValue(':paid_at', $paidAt, PDO::PARAM_STR);
        $st->bindValue(':status', $status, PDO::PARAM_STR);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $st->execute();
    }

    public function updateCashFlowStatus(int $tenantId, int $invoiceId, string $status): void
    {
        $sql = 'UPDATE financial_entries
                SET status = :status
                WHERE tenant_id = :tenant_id AND invoice_id = :invoice_id';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':status', $status, PDO::PARAM_STR);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $st->execute();
    }

    public function listByProject(int $tenantId, int $projectId): array
    {
        $sql = 'SELECT i.id, i.code, i.description, i.amount_total, i.amount_paid,
                       CASE WHEN status <> "paid" AND due_date < CURDATE() THEN "overdue" ELSE status END AS status,
                       i.installment_type, i.installment_number, i.reference_month, i.reference_year, i.due_date, i.paid_at,
                       pay.id AS payment_id, pay.receipt_path
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
                  AND i.project_id = :project_id
                ORDER BY i.due_date ASC, i.id ASC';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function findInstallmentPlanByProject(int $tenantId, int $projectId): ?array
    {
        $st = DB::pdo()->prepare('SELECT * FROM installment_plans WHERE tenant_id = :tenant_id AND project_id = :project_id LIMIT 1');
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return is_array($row) ? $row : null;
    }

    public function createInstallmentPlan(array $data): int
    {
        $sql = 'INSERT INTO installment_plans (
                    tenant_id, project_id, valor_total, valor_entrada, data_entrada, saldo_restante,
                    quantidade_parcelas, valor_parcela, data_primeira_parcela, formas_pagamento, created_by_user_id
                ) VALUES (
                    :tenant_id, :project_id, :valor_total, :valor_entrada, :data_entrada, :saldo_restante,
                    :quantidade_parcelas, :valor_parcela, :data_primeira_parcela, :formas_pagamento, :created_by_user_id
                )';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', (int)$data['tenant_id'], PDO::PARAM_INT);
        $st->bindValue(':project_id', (int)$data['project_id'], PDO::PARAM_INT);
        $st->bindValue(':valor_total', $data['valor_total']);
        $st->bindValue(':valor_entrada', $data['valor_entrada'] !== null ? $data['valor_entrada'] : null, $data['valor_entrada'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':data_entrada', $data['data_entrada'] !== null ? $data['data_entrada'] : null, $data['data_entrada'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':saldo_restante', $data['saldo_restante']);
        $st->bindValue(':quantidade_parcelas', (int)$data['quantidade_parcelas'], PDO::PARAM_INT);
        $st->bindValue(':valor_parcela', $data['valor_parcela']);
        $st->bindValue(':data_primeira_parcela', $data['data_primeira_parcela'] !== null ? $data['data_primeira_parcela'] : null, $data['data_primeira_parcela'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':formas_pagamento', $data['formas_pagamento'] !== '' ? $data['formas_pagamento'] : null, $data['formas_pagamento'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':created_by_user_id', $data['created_by_user_id'] ?: null, $data['created_by_user_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->execute();
        return (int)DB::pdo()->lastInsertId();
    }

    public function projectsForInstallmentPlanning(int $tenantId): array
    {
        $sql = 'SELECT p.id, p.client_id, p.name, p.contract_value, c.name AS client_name
                FROM projects p
                INNER JOIN clients c ON c.id = p.client_id
                WHERE p.tenant_id = :tenant_id
                ORDER BY p.created_at DESC, p.id DESC';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function clientsWithFutureInstallments(int $tenantId): array
    {
        $sql = 'SELECT DISTINCT c.id, c.name
                FROM invoices i
                INNER JOIN clients c ON c.id = i.client_id
                WHERE i.tenant_id = :tenant_id
                  AND i.status <> "paid"
                  AND i.amount_total > i.amount_paid
                  AND i.due_date > CURDATE()
                ORDER BY c.name ASC';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function projectsWithFutureInstallments(int $tenantId, ?int $clientId = null): array
    {
        $sql = 'SELECT DISTINCT p.id, p.client_id, p.name, c.name AS client_name
                FROM invoices i
                INNER JOIN projects p ON p.id = i.project_id
                INNER JOIN clients c ON c.id = i.client_id
                WHERE i.tenant_id = :tenant_id
                  AND i.status <> "paid"
                  AND i.amount_total > i.amount_paid
                  AND i.due_date > CURDATE()';

        if ($clientId !== null && $clientId > 0) {
            $sql .= ' AND i.client_id = :client_id';
        }

        $sql .= ' ORDER BY p.name ASC';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($clientId !== null && $clientId > 0) {
            $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        }
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function listAnticipationCandidates(int $tenantId, ?int $clientId = null, ?int $projectId = null): array
    {
        if (($clientId ?? 0) <= 0 && ($projectId ?? 0) <= 0) {
            return [];
        }

        $sql = 'SELECT i.id, i.code, i.description, i.amount_total, i.amount_paid, i.status, i.installment_number, i.due_date,
                       i.client_id, i.project_id, c.name AS client_name, p.name AS project_name
                FROM invoices i
                INNER JOIN clients c ON c.id = i.client_id
                LEFT JOIN projects p ON p.id = i.project_id
                WHERE i.tenant_id = :tenant_id
                  AND i.status <> "paid"
                  AND i.amount_total > i.amount_paid
                  AND i.due_date > CURDATE()';

        if ($clientId !== null && $clientId > 0) {
            $sql .= ' AND i.client_id = :client_id';
        }
        if ($projectId !== null && $projectId > 0) {
            $sql .= ' AND i.project_id = :project_id';
        }

        $sql .= ' ORDER BY i.due_date ASC, i.id ASC';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($clientId !== null && $clientId > 0) {
            $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        }
        if ($projectId !== null && $projectId > 0) {
            $st->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        }
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function findAnticipationInvoicesByIds(int $tenantId, array $invoiceIds, ?int $clientId = null, ?int $projectId = null): array
    {
        $invoiceIds = array_values(array_unique(array_map(static fn(mixed $value): int => (int)$value, $invoiceIds)));
        $invoiceIds = array_values(array_filter($invoiceIds, static fn(int $value): bool => $value > 0));
        if ($invoiceIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [':tenant_id' => $tenantId];
        foreach ($invoiceIds as $index => $invoiceId) {
            $key = ':invoice_' . $index;
            $placeholders[] = $key;
            $params[$key] = $invoiceId;
        }

        $sql = 'SELECT i.id, i.code, i.description, i.amount_total, i.amount_paid, i.status, i.installment_number, i.due_date,
                       i.client_id, i.project_id, c.name AS client_name, p.name AS project_name
                FROM invoices i
                INNER JOIN clients c ON c.id = i.client_id
                LEFT JOIN projects p ON p.id = i.project_id
                WHERE i.tenant_id = :tenant_id
                  AND i.id IN (' . implode(', ', $placeholders) . ')
                  AND i.status <> "paid"
                  AND i.amount_total > i.amount_paid
                  AND i.due_date > CURDATE()';

        if ($clientId !== null && $clientId > 0) {
            $sql .= ' AND i.client_id = :client_id';
            $params[':client_id'] = $clientId;
        }
        if ($projectId !== null && $projectId > 0) {
            $sql .= ' AND i.project_id = :project_id';
            $params[':project_id'] = $projectId;
        }

        $sql .= ' ORDER BY i.due_date ASC, i.id ASC';
        $st = DB::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $st->bindValue($key, $value, PDO::PARAM_INT);
        }
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function createInstallmentAnticipation(array $data): int
    {
        $sql = 'INSERT INTO installment_anticipations (
                    tenant_id, client_id, project_id, adjustment_mode, adjustment_rate, base_amount,
                    adjustment_amount, total_amount, payment_date, payment_method, note, processed_by_user_id
                ) VALUES (
                    :tenant_id, :client_id, :project_id, :adjustment_mode, :adjustment_rate, :base_amount,
                    :adjustment_amount, :total_amount, :payment_date, :payment_method, :note, :processed_by_user_id
                )';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', (int)$data['tenant_id'], PDO::PARAM_INT);
        $st->bindValue(':client_id', (int)$data['client_id'], PDO::PARAM_INT);
        $st->bindValue(':project_id', $data['project_id'] !== null ? (int)$data['project_id'] : null, $data['project_id'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':adjustment_mode', (string)$data['adjustment_mode'], PDO::PARAM_STR);
        $st->bindValue(':adjustment_rate', $data['adjustment_rate']);
        $st->bindValue(':base_amount', $data['base_amount']);
        $st->bindValue(':adjustment_amount', $data['adjustment_amount']);
        $st->bindValue(':total_amount', $data['total_amount']);
        $st->bindValue(':payment_date', (string)$data['payment_date'], PDO::PARAM_STR);
        $st->bindValue(':payment_method', (string)$data['payment_method'], PDO::PARAM_STR);
        $st->bindValue(':note', $data['note'] !== '' ? (string)$data['note'] : null, $data['note'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':processed_by_user_id', $data['processed_by_user_id'] !== null ? (int)$data['processed_by_user_id'] : null, $data['processed_by_user_id'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->execute();
        return (int)DB::pdo()->lastInsertId();
    }

    public function createInstallmentAnticipationItem(array $data): int
    {
        $sql = 'INSERT INTO installment_anticipation_items (
                    anticipation_id, invoice_id, payment_id, original_due_date, original_amount_total, original_amount_paid,
                    settled_amount, payment_amount, adjustment_amount, receipt_number
                ) VALUES (
                    :anticipation_id, :invoice_id, :payment_id, :original_due_date, :original_amount_total, :original_amount_paid,
                    :settled_amount, :payment_amount, :adjustment_amount, :receipt_number
                )';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':anticipation_id', (int)$data['anticipation_id'], PDO::PARAM_INT);
        $st->bindValue(':invoice_id', (int)$data['invoice_id'], PDO::PARAM_INT);
        $st->bindValue(':payment_id', (int)$data['payment_id'], PDO::PARAM_INT);
        $st->bindValue(':original_due_date', (string)$data['original_due_date'], PDO::PARAM_STR);
        $st->bindValue(':original_amount_total', $data['original_amount_total']);
        $st->bindValue(':original_amount_paid', $data['original_amount_paid']);
        $st->bindValue(':settled_amount', $data['settled_amount']);
        $st->bindValue(':payment_amount', $data['payment_amount']);
        $st->bindValue(':adjustment_amount', $data['adjustment_amount']);
        $st->bindValue(':receipt_number', $data['receipt_number'] !== '' ? (string)$data['receipt_number'] : null, $data['receipt_number'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->execute();
        return (int)DB::pdo()->lastInsertId();
    }

    public function listRecentAnticipations(int $tenantId, int $limit = 10): array
    {
        $sql = 'SELECT ia.id, ia.client_id, ia.project_id, ia.adjustment_mode, ia.adjustment_rate, ia.base_amount,
                       ia.adjustment_amount, ia.total_amount, ia.payment_date, ia.payment_method, ia.created_at,
                       c.name AS client_name, p.name AS project_name,
                       (SELECT COUNT(*) FROM installment_anticipation_items item WHERE item.anticipation_id = ia.id) AS installments_count
                FROM installment_anticipations ia
                INNER JOIN clients c ON c.id = ia.client_id
                LEFT JOIN projects p ON p.id = ia.project_id
                WHERE ia.tenant_id = :tenant_id
                ORDER BY ia.id DESC
                LIMIT :limit';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function settleInvoiceAsAnticipated(int $tenantId, int $invoiceId, string $paidAt): void
    {
        $sql = 'UPDATE invoices
                SET amount_paid = amount_total, paid_at = :paid_at, status = "paid"
                WHERE tenant_id = :tenant_id AND id = :invoice_id';
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':paid_at', $paidAt, PDO::PARAM_STR);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $st->execute();
    }

    private function paidAmountByPeriod(int $tenantId, ?int $month, ?int $year): float
    {
        $sql = 'SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE tenant_id = :tenant_id';
        if ($month !== null && $year !== null) {
            $sql .= ' AND MONTH(paid_at) = :month AND YEAR(paid_at) = :year';
        }
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($month !== null && $year !== null) {
            $st->bindValue(':month', $month, PDO::PARAM_INT);
            $st->bindValue(':year', $year, PDO::PARAM_INT);
        }
        $st->execute();
        return (float)($st->fetch()['total'] ?? 0);
    }

    private function pendingAmountByPeriod(int $tenantId, ?int $month, ?int $year): float
    {
        $sql = 'SELECT COALESCE(SUM(amount_total - amount_paid), 0) AS total
                FROM invoices
                WHERE tenant_id = :tenant_id
                  AND status <> "paid"
                  AND due_date >= CURDATE()';
        if ($month !== null && $year !== null) {
            $sql .= ' AND reference_month = :month AND reference_year = :year';
        }
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($month !== null && $year !== null) {
            $st->bindValue(':month', $month, PDO::PARAM_INT);
            $st->bindValue(':year', $year, PDO::PARAM_INT);
        }
        $st->execute();
        return (float)($st->fetch()['total'] ?? 0);
    }

    private function overdueAmountByPeriod(int $tenantId, ?int $month, ?int $year): float
    {
        $sql = 'SELECT COALESCE(SUM(amount_total - amount_paid), 0) AS total
                FROM invoices
                WHERE tenant_id = :tenant_id
                  AND status <> "paid"
                  AND due_date < CURDATE()';
        if ($month !== null && $year !== null) {
            $sql .= ' AND reference_month = :month AND reference_year = :year';
        }
        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($month !== null && $year !== null) {
            $st->bindValue(':month', $month, PDO::PARAM_INT);
            $st->bindValue(':year', $year, PDO::PARAM_INT);
        }
        $st->execute();
        return (float)($st->fetch()['total'] ?? 0);
    }
}
