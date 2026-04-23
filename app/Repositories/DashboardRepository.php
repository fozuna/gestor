<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\DB;
use PDO;

final class DashboardRepository
{
    public function kpis(int $tenantId): array
    {
        $dashboard = $this->dashboardData($tenantId, (int)date('Y'), 'active', 0);
        return [
            'projectsActive' => (int)($dashboard['projects_count'] ?? 0),
            'tasksOpen' => (int)($dashboard['tasks_pending_total'] ?? 0),
            'incomeMonth' => (float)($dashboard['total_received_year'] ?? 0) / 12.0,
            'balanceMonth' => (float)($dashboard['total_received_year'] ?? 0) / 12.0,
        ];
    }

    public function dashboardData(int $tenantId, int $year, string $projectStatus, int $clientId): array
    {
        $totals = $this->annualTotals($tenantId, $year, $projectStatus, $clientId);
        $monthly = $this->monthlyFinancialSeries($tenantId, $year, $projectStatus, $clientId);
        $projects = $this->projectFinancialSnapshot($tenantId, $year, $projectStatus, $clientId);

        $tasksPendingTotal = 0;
        foreach ($projects as $row) {
            $tasksPendingTotal += (int)($row['tasks_pending'] ?? 0);
        }

        return [
            'total_billed_year' => (float)($totals['billed'] ?? 0),
            'total_received_year' => (float)($totals['received'] ?? 0),
            'total_open_year' => (float)($totals['open'] ?? 0),
            'balance_year' => (float)($totals['received'] ?? 0),
            'monthly_predicted' => $monthly['predicted'],
            'monthly_realized' => $monthly['realized'],
            'projects' => $projects,
            'projects_count' => count($projects),
            'tasks_pending_total' => $tasksPendingTotal,
        ];
    }

    private function annualTotals(int $tenantId, int $year, string $projectStatus, int $clientId): array
    {
        $whereInvoice = 'i.tenant_id = :tenant_id AND YEAR(i.due_date) = :ref_year';
        $wherePayment = 'pay.tenant_id = :tenant_id AND YEAR(pay.paid_at) = :ref_year';

        $params = [
            ':tenant_id' => $tenantId,
            ':ref_year' => $year,
        ];

        if ($projectStatus !== '') {
            $whereInvoice .= ' AND p.status = :project_status';
            $wherePayment .= ' AND p.status = :project_status';
            $params[':project_status'] = $projectStatus;
        }
        if ($clientId > 0) {
            $whereInvoice .= ' AND i.client_id = :client_id';
            $wherePayment .= ' AND i.client_id = :client_id';
            $params[':client_id'] = $clientId;
        }

        $sqlInvoice = 'SELECT
                         COALESCE(SUM(i.amount_total), 0) AS billed,
                         COALESCE(SUM(GREATEST(i.amount_total - LEAST(i.amount_paid, i.amount_total), 0)), 0) AS open_total
                       FROM invoices i
                       LEFT JOIN projects p ON p.id = i.project_id
                       WHERE ' . $whereInvoice;
        $stInv = DB::pdo()->prepare($sqlInvoice);
        foreach ($params as $key => $value) {
            $stInv->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stInv->execute();
        $invoiceRow = $stInv->fetch();

        $sqlPayment = 'SELECT COALESCE(SUM(pay.amount), 0) AS received
                       FROM payments pay
                       INNER JOIN invoices i ON i.id = pay.invoice_id
                       LEFT JOIN projects p ON p.id = i.project_id
                       WHERE ' . $wherePayment;
        $stPay = DB::pdo()->prepare($sqlPayment);
        foreach ($params as $key => $value) {
            $stPay->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stPay->execute();
        $paymentRow = $stPay->fetch();

        return [
            'billed' => (float)($invoiceRow['billed'] ?? 0),
            'open' => (float)($invoiceRow['open_total'] ?? 0),
            'received' => (float)($paymentRow['received'] ?? 0),
        ];
    }

    private function monthlyFinancialSeries(int $tenantId, int $year, string $projectStatus, int $clientId): array
    {
        $predicted = array_fill(1, 12, 0.0);
        $realized = array_fill(1, 12, 0.0);

        $whereInvoice = 'i.tenant_id = :tenant_id AND YEAR(i.due_date) = :ref_year';
        $wherePayment = 'pay.tenant_id = :tenant_id AND YEAR(pay.paid_at) = :ref_year';
        $params = [
            ':tenant_id' => $tenantId,
            ':ref_year' => $year,
        ];

        if ($projectStatus !== '') {
            $whereInvoice .= ' AND p.status = :project_status';
            $wherePayment .= ' AND p.status = :project_status';
            $params[':project_status'] = $projectStatus;
        }
        if ($clientId > 0) {
            $whereInvoice .= ' AND i.client_id = :client_id';
            $wherePayment .= ' AND i.client_id = :client_id';
            $params[':client_id'] = $clientId;
        }

        $sqlPredicted = 'SELECT MONTH(i.due_date) AS ref_month, COALESCE(SUM(i.amount_total), 0) AS amount
                         FROM invoices i
                         LEFT JOIN projects p ON p.id = i.project_id
                         WHERE ' . $whereInvoice . '
                         GROUP BY MONTH(i.due_date)';
        $stPred = DB::pdo()->prepare($sqlPredicted);
        foreach ($params as $key => $value) {
            $stPred->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stPred->execute();
        foreach ((array)$stPred->fetchAll() as $row) {
            $m = (int)($row['ref_month'] ?? 0);
            if ($m >= 1 && $m <= 12) {
                $predicted[$m] = (float)($row['amount'] ?? 0);
            }
        }

        $sqlRealized = 'SELECT MONTH(pay.paid_at) AS ref_month, COALESCE(SUM(pay.amount), 0) AS amount
                        FROM payments pay
                        INNER JOIN invoices i ON i.id = pay.invoice_id
                        LEFT JOIN projects p ON p.id = i.project_id
                        WHERE ' . $wherePayment . '
                        GROUP BY MONTH(pay.paid_at)';
        $stReal = DB::pdo()->prepare($sqlRealized);
        foreach ($params as $key => $value) {
            $stReal->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stReal->execute();
        foreach ((array)$stReal->fetchAll() as $row) {
            $m = (int)($row['ref_month'] ?? 0);
            if ($m >= 1 && $m <= 12) {
                $realized[$m] = (float)($row['amount'] ?? 0);
            }
        }

        return [
            'predicted' => array_values($predicted),
            'realized' => array_values($realized),
        ];
    }

    private function projectFinancialSnapshot(int $tenantId, int $year, string $projectStatus, int $clientId): array
    {
        $sql = 'SELECT p.id, p.name, p.status, c.name AS client_name,
                       COALESCE(fin.total_value, 0) AS total_value,
                       COALESCE(fin.received_value, 0) AS received_value,
                       GREATEST(COALESCE(fin.total_value, 0) - COALESCE(fin.received_value, 0), 0) AS open_value,
                       COALESCE(ts.total_tasks, 0) AS tasks_total,
                       COALESCE(ts.done_tasks, 0) AS tasks_done,
                       GREATEST(COALESCE(ts.total_tasks, 0) - COALESCE(ts.done_tasks, 0), 0) AS tasks_pending
                FROM projects p
                INNER JOIN clients c ON c.id = p.client_id
                LEFT JOIN (
                    SELECT i.project_id,
                           COALESCE(SUM(i.amount_total), 0) AS total_value,
                           COALESCE(SUM(LEAST(i.amount_paid, i.amount_total)), 0) AS received_value
                    FROM invoices i
                    WHERE i.tenant_id = :tenant_id_fin
                      AND YEAR(i.due_date) = :ref_year_fin
                    GROUP BY i.project_id
                ) fin ON fin.project_id = p.id
                LEFT JOIN (
                    SELECT t.project_id,
                           COUNT(*) AS total_tasks,
                           SUM(CASE WHEN t.status = "done" THEN 1 ELSE 0 END) AS done_tasks
                    FROM tasks t
                    WHERE t.tenant_id = :tenant_id_tasks
                    GROUP BY t.project_id
                ) ts ON ts.project_id = p.id
                WHERE p.tenant_id = :tenant_id';

        if ($projectStatus !== '') {
            $sql .= ' AND p.status = :project_status';
        }
        if ($clientId > 0) {
            $sql .= ' AND p.client_id = :client_id';
        }

        $sql .= ' ORDER BY p.created_at DESC, p.id DESC LIMIT 80';

        $st = DB::pdo()->prepare($sql);
        $st->bindValue(':tenant_id_fin', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':ref_year_fin', $year, PDO::PARAM_INT);
        $st->bindValue(':tenant_id_tasks', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($projectStatus !== '') {
            $st->bindValue(':project_status', $projectStatus, PDO::PARAM_STR);
        }
        if ($clientId > 0) {
            $st->bindValue(':client_id', $clientId, PDO::PARAM_INT);
        }
        $st->execute();
        $rows = $st->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}

