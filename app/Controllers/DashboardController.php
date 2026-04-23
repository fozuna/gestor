<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\View;
use App\Helpers\Security;
use App\Helpers\Session;
use App\Services\ClientService;
use App\Services\DashboardService;

final class DashboardController
{
    public function index(Request $request): void
    {
        $tenantId = Session::get('tenant_id');
        $tenantId = is_int($tenantId) ? $tenantId : (int)$tenantId;
        $currentYear = (int)date('Y');
        $filters = [
            'year' => max(2020, min(2100, (int)($request->query('year', (string)$currentYear) ?? (string)$currentYear))),
            'project_status' => Security::sanitizeString((string)($request->query('project_status', 'active') ?? 'active')),
            'client_id' => max(0, (int)($request->query('client_id', '0') ?? '0')),
        ];
        $dashboard = [
            'total_billed_year' => 0.0,
            'total_received_year' => 0.0,
            'total_open_year' => 0.0,
            'balance_year' => 0.0,
            'monthly_predicted' => array_fill(0, 12, 0.0),
            'monthly_realized' => array_fill(0, 12, 0.0),
            'projects' => [],
            'projects_count' => 0,
            'tasks_pending_total' => 0,
            'receive_rate' => 0.0,
            'avg_monthly_billed' => 0.0,
            'best_month_label' => 'Jan',
            'best_month_value' => 0.0,
            'worst_month_label' => 'Jan',
            'worst_month_value' => 0.0,
            'months_labels' => ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
        ];
        $clients = [];
        $error = null;

        try {
            $dashboard = (new DashboardService())->dashboard($tenantId, $filters);
            $clients = (new ClientService())->optionsByTenant($tenantId);
        } catch (\Throwable $e) {
            $error = 'Não foi possível carregar o dashboard agora: ' . Security::sanitizeString($e->getMessage());
        }

        $availableYears = [];
        for ($y = $currentYear; $y >= $currentYear - 5; $y -= 1) {
            $availableYears[] = $y;
        }

        View::render('dashboard/index', [
            'dashboard' => $dashboard,
            'filters' => $filters,
            'clients' => $clients,
            'availableYears' => $availableYears,
            'tenantName' => (string)Session::get('tenant_name', 'TRAXTER'),
            'role' => (string)Session::get('role', 'user'),
            'error' => $error,
        ]);
    }
}

