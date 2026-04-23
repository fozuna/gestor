<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Helpers\Flash;
use App\Helpers\Security;
use App\Helpers\Session;
use App\Services\ClientService;
use App\Services\ProjectAccessService;
use App\Services\ProjectService;
use App\Services\TaskService;

final class ProjectController
{
    public function index(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $clients = [];
        $projects = [];
        $tasksBoard = [
            'todo' => [],
            'doing' => [],
            'done' => [],
        ];
        $projectOptions = [];
        $ok = Flash::get('ok');
        $error = Flash::get('error');

        try {
            $clients = (new ClientService())->optionsByTenant($tenantId);
            $projects = (new ProjectService())->listByTenant($tenantId);
            $tasksBoard = (new TaskService())->boardByTenant($tenantId);
            $projectOptions = (new ProjectService())->optionsByTenant($tenantId);
        } catch (\Throwable $e) {
            $error = 'Não foi possível carregar projetos e tarefas agora: ' . Security::sanitizeString($e->getMessage());
        }

        View::render('projects/index', [
            'clients' => $clients,
            'projects' => $projects,
            'tasksBoard' => $tasksBoard,
            'projectOptions' => $projectOptions,
            'suggestedInstallments' => [],
            'tenantName' => (string)Session::get('tenant_name', 'TRAXTER'),
            'role' => (string)Session::get('role', 'user'),
            'ok' => $ok,
            'error' => $error,
        ]);
    }

    public function show(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $role = (string)Session::get('role', 'user');
        $userId = (int)Session::get('user_id', 0);
        $projectId = (int)($request->query('id', '0') ?? '0');

        if (!(new ProjectAccessService())->canView($role)) {
            Response::html('Acesso negado.', 403);
            return;
        }

        try {
            if ($projectId <= 0) {
                throw new \RuntimeException('Projeto inválido.');
            }

            $detail = (new ProjectService())->detail($tenantId, $projectId, $userId > 0 ? $userId : null);
            if ($detail === null) {
                Response::html('Projeto não encontrado.', 404);
                return;
            }

            View::render('projects/show', [
                'detail' => $detail,
                'taskSort' => 'created_at',
                'tab' => 'details',
                'users' => [],
                'editTask' => null,
                'tenantName' => (string)Session::get('tenant_name', 'TRAXTER'),
                'role' => $role,
                'ok' => Flash::get('ok'),
                'error' => Flash::get('error'),
            ]);
        } catch (\Throwable $e) {
            Flash::set('error', Security::sanitizeString($e->getMessage()));
            Response::redirect('/projects');
        }
    }

    public function store(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);

        try {
            (new ProjectService())->create(
                $tenantId,
                (int)($request->input('client_id') ?? 0),
                $userId > 0 ? $userId : null,
                [
                    'name' => Security::sanitizeString($request->input('name') ?? ''),
                    'description' => Security::sanitizeString($request->input('description') ?? ''),
                    'status' => Security::sanitizeString($request->input('status') ?? 'active'),
                    'start_date' => (string)($request->input('start_date') ?? ''),
                    'due_date' => (string)($request->input('due_date') ?? ''),
                    'contract_value' => Security::parseMoney($request->input('contract_value') ?? '0'),
                    'payment_terms' => Security::sanitizeString($request->input('payment_terms') ?? ''),
                    'entry_amount' => Security::parseMoney($request->input('entry_amount') ?? '0'),
                    'first_installment_date' => (string)($request->input('first_installment_date') ?? ''),
                    'installments' => array_values(array_filter(
                        is_array($request->all()['installments'] ?? null) ? $request->all()['installments'] : [],
                        static fn($row): bool => is_array($row)
                    )),
                ]
            );
            Flash::set('ok', 'Projeto criado com sucesso.');
        } catch (\Throwable $e) {
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }

        Response::redirect('/projects');
    }
}
