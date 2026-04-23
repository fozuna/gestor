<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Helpers\Flash;
use App\Helpers\Security;
use App\Helpers\Session;
use App\Services\ClientProfileService;
use App\Services\ClientService;
use App\Services\ClientTasksPdfReportService;

final class ClientController
{
    public function index(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $clients = [];
        $editClient = null;
        $ok = Flash::get('ok');
        $error = Flash::get('error');

        try {
            $service = new ClientService();
            $clients = $service->listByTenant($tenantId);
            $editId = (int)($request->query('edit', '0') ?? '0');
            if ($editId > 0) {
                $editClient = $service->findById($tenantId, $editId);
            }
        } catch (\Throwable $e) {
            $error = 'Não foi possível carregar os clientes agora: ' . Security::sanitizeString($e->getMessage());
        }

        View::render('clients/index', [
            'clients' => $clients,
            'tenantName' => (string)Session::get('tenant_name', 'TRAXTER'),
            'role' => (string)Session::get('role', 'user'),
            'ok' => $ok,
            'error' => $error,
            'editClient' => $editClient,
        ]);
    }

    public function profile(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $clientId = (int)($request->query('id', '0') ?? '0');
        $startDateRaw = Security::sanitizeString((string)($request->query('start_date', '') ?? ''));
        $endDateRaw = Security::sanitizeString((string)($request->query('end_date', '') ?? ''));
        $startDate = $startDateRaw !== '' ? Security::parseDate($startDateRaw) : date('Y-m-01', strtotime('-5 months'));
        $endDate = $endDateRaw !== '' ? Security::parseDate($endDateRaw) : date('Y-m-d');
        $error = null;
        $profile = [
            'client' => null,
            'financial' => [
                'pending_installments' => 0,
                'pending_total' => 0,
                'overdue_total' => 0,
            ],
            'installmentsHistory' => [],
            'installments' => [],
            'projects' => [],
            'billableTasks' => [],
            'informativeTasks' => [],
            'billableTasksTotal' => 0,
        ];

        try {
            if ($clientId <= 0) {
                throw new \RuntimeException('Cliente inválido.');
            }

            if ($startDate === null || $endDate === null) {
                throw new \RuntimeException('Informe um intervalo de datas válido.');
            }

            if ($startDate > $endDate) {
                throw new \RuntimeException('A data inicial não pode ser maior que a data final.');
            }

            $profile = (new ClientProfileService())->build($tenantId, $clientId, $startDate, $endDate);
            if ($profile['client'] === null) {
                throw new \RuntimeException('Cliente não encontrado.');
            }
        } catch (\Throwable $e) {
            $error = 'Não foi possível carregar o perfil do cliente: ' . Security::sanitizeString($e->getMessage());
        }

        View::render('clients/profile', [
            'profile' => $profile,
            'tenantName' => (string)Session::get('tenant_name', 'TRAXTER'),
            'role' => (string)Session::get('role', 'user'),
            'filter' => [
                'start_date' => $startDate ?? '',
                'end_date' => $endDate ?? '',
            ],
            'error' => $error,
            'ok' => Flash::get('ok'),
        ]);
    }

    public function store(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);

        try {
            (new ClientService())->create($tenantId, [
                'name' => Security::sanitizeString($request->input('name') ?? ''),
                'email' => Security::sanitizeEmail($request->input('email') ?? ''),
                'phone' => Security::sanitizeString($request->input('phone') ?? ''),
                'status' => Security::sanitizeString($request->input('status') ?? 'active'),
                'notes' => Security::sanitizeString($request->input('notes') ?? ''),
            ]);
            Flash::set('ok', 'Cliente criado com sucesso.');
        } catch (\Throwable $e) {
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }

        Response::redirect('/clients');
    }

    public function update(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $clientId = (int)($request->route('id', '0') ?? '0');
        try {
            (new ClientService())->update($tenantId, $clientId, [
                'name' => Security::sanitizeString((string)($request->input('name') ?? '')),
                'email' => Security::sanitizeEmail((string)($request->input('email') ?? '')),
                'phone' => Security::sanitizeString((string)($request->input('phone') ?? '')),
                'status' => Security::sanitizeString((string)($request->input('status') ?? 'active')),
                'notes' => Security::sanitizeString((string)($request->input('notes') ?? '')),
            ]);
            Flash::set('ok', 'Cliente atualizado com sucesso.');
        } catch (\Throwable $e) {
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }
        Response::redirect('/clients');
    }

    public function destroy(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $clientId = (int)($request->route('id', '0') ?? '0');
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');

        try {
            (new ClientService())->delete($tenantId, $clientId);
            if (str_contains(strtolower($accept), 'application/json')) {
                Response::json(['success' => true, 'message' => 'Cliente excluído com sucesso.']);
                return;
            }
            Flash::set('ok', 'Cliente excluído com sucesso.');
        } catch (\Throwable $e) {
            $message = Security::sanitizeString($e->getMessage());
            if (str_contains(strtolower($accept), 'application/json')) {
                Response::json(['success' => false, 'message' => $message], 422);
                return;
            }
            Flash::set('error', $message);
        }

        Response::redirect('/clients');
    }

    public function exportTasksPdf(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $clientId = (int)($request->query('id', '0') ?? '0');
        $startDateRaw = Security::sanitizeString((string)($request->query('start_date', '') ?? ''));
        $endDateRaw = Security::sanitizeString((string)($request->query('end_date', '') ?? ''));
        $startDate = $startDateRaw !== '' ? Security::parseDate($startDateRaw) : date('Y-m-01', strtotime('-5 months'));
        $endDate = $endDateRaw !== '' ? Security::parseDate($endDateRaw) : date('Y-m-d');

        try {
            if ($clientId <= 0) {
                throw new \RuntimeException('Cliente inválido para exportação.');
            }
            if ($startDate === null || $endDate === null) {
                throw new \RuntimeException('Informe um intervalo de datas válido para exportação.');
            }
            if ($startDate > $endDate) {
                throw new \RuntimeException('A data inicial não pode ser maior que a data final.');
            }

            $report = (new ClientTasksPdfReportService())->generateForClient(
                $tenantId,
                $clientId,
                $startDate,
                $endDate,
                (string)Session::get('tenant_name', 'TRAXTER')
            );

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $report['filename'] . '"');
            header('Content-Length: ' . strlen((string)$report['content']));
            echo $report['content'];
            exit;
        } catch (\Throwable $e) {
            Flash::set('error', 'Falha ao gerar PDF: ' . Security::sanitizeString($e->getMessage()));
            Response::redirect('/clients/profile?id=' . $clientId . '&start_date=' . urlencode((string)$startDate) . '&end_date=' . urlencode((string)$endDate));
        }
    }
}
