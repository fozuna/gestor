<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Helpers\Flash;
use App\Helpers\Security;
use App\Helpers\Session;
use App\Repositories\UserRepository;
use App\Services\ClientService;
use App\Services\ProjectAccessService;
use App\Services\ProjectService;
use App\Services\TaskService;

final class ProjectsModuleController
{
    public function index(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $role = (string)Session::get('role', 'user');
        if (!(new ProjectAccessService())->canView($role)) {
            Response::html('Acesso negado.', 403);
            return;
        }

        $filters = [
            'search' => Security::sanitizeString((string)($request->query('search', '') ?? '')),
            'status' => Security::sanitizeString((string)($request->query('status', '') ?? '')),
            'page' => max(1, (int)($request->query('page', '1') ?? '1')),
            'per_page' => 8,
        ];

        $projects = (new ProjectService())->paginatedList($tenantId, $filters);
        $clients = (new ClientService())->optionsByTenant($tenantId);
        $editProject = null;
        $editId = (int)($request->query('edit', '0') ?? '0');
        if ($editId > 0) {
            $editProject = (new ProjectService())->detail($tenantId, $editId);
        }

        View::render('projects/module', [
            'projects' => $projects['items'],
            'pagination' => $projects['pagination'],
            'filters' => $filters,
            'clients' => $clients,
            'editProject' => $editProject['project'] ?? null,
            'editInstallments' => $editProject['installments'] ?? [],
            'tenantName' => (string)Session::get('tenant_name', 'TRAXTER'),
            'role' => $role,
            'ok' => Flash::get('ok'),
            'error' => Flash::get('error'),
        ]);
    }

    public function show(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $role = (string)Session::get('role', 'user');
        if (!(new ProjectAccessService())->canView($role)) {
            Response::html('Acesso negado.', 403);
            return;
        }

        $projectId = (int)($request->route('id', '0') ?? '0');
        $userId = (int)Session::get('user_id', 0);
        $sort = Security::sanitizeString((string)($request->query('task_sort', 'created_at') ?? 'created_at'));
        $tab = Security::sanitizeString((string)($request->query('tab', 'dashboard') ?? 'dashboard'));
        $detail = (new ProjectService())->detail($tenantId, $projectId, $userId > 0 ? $userId : null);
        if ($detail === null) {
            Response::html('Projeto não encontrado.', 404);
            return;
        }

        $detail['tasks'] = (new TaskService())->listByProjectPaginated($tenantId, $projectId, $sort, 50);
        $editTask = null;
        $editTaskId = (int)($request->query('edit_task', '0') ?? '0');
        if ($editTaskId > 0) {
            $editTask = (new TaskService())->detail($tenantId, $editTaskId);
            if ($editTask !== null && (int)($editTask['project_id'] ?? 0) !== $projectId) {
                $editTask = null;
            }
        }

        View::render('projects/show', [
            'detail' => $detail,
            'taskSort' => $sort,
            'tab' => $tab,
            'users' => (new UserRepository())->listByTenant($tenantId),
            'editTask' => $editTask,
            'tenantName' => (string)Session::get('tenant_name', 'TRAXTER'),
            'role' => $role,
            'ok' => Flash::get('ok'),
            'error' => Flash::get('error'),
        ]);
    }

    public function store(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);
        $role = (string)Session::get('role', 'user');
        if (!(new ProjectAccessService())->canManage($role)) {
            Flash::set('error', 'Você não tem permissão para criar projetos.');
            Response::redirect('/projetos');
            return;
        }

        try {
            $post = $request->all();
            $installments = is_array($post['installments'] ?? null) ? $post['installments'] : [];
            (new ProjectService())->create(
                $tenantId,
                (int)($request->input('client_id') ?? 0),
                $userId > 0 ? $userId : null,
                [
                    'name' => Security::sanitizeString((string)($request->input('name') ?? '')),
                    'description' => Security::sanitizeString((string)($request->input('description') ?? '')),
                    'status' => Security::sanitizeString((string)($request->input('status') ?? 'active')),
                    'start_date' => (string)($request->input('start_date') ?? ''),
                    'due_date' => (string)($request->input('due_date') ?? ''),
                    'contract_value' => (string)($request->input('contract_value') ?? '0'),
                    'entry_amount' => (string)($request->input('entry_amount') ?? '0'),
                    'payment_terms' => Security::sanitizeString((string)($request->input('payment_terms') ?? '')),
                    'installments_count' => (int)($request->input('installments_count') ?? 0),
                    'first_installment_date' => (string)($request->input('first_installment_date') ?? ''),
                    'installments' => array_values(array_filter($installments, static fn($row): bool => is_array($row))),
                ]
            );
            Flash::set('ok', 'Projeto criado com sucesso.');
        } catch (\Throwable $e) {
            error_log('[ProjectsModuleController] ' . $e->getMessage());
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }

        Response::redirect('/projetos');
    }

    public function update(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);
        $role = (string)Session::get('role', 'user');
        if (!(new ProjectAccessService())->canManage($role)) {
            Flash::set('error', 'Você não tem permissão para editar projetos.');
            Response::redirect('/projetos');
            return;
        }

        $projectId = (int)($request->route('id', '0') ?? '0');

        try {
            $post = $request->all();
            $installments = is_array($post['installments'] ?? null) ? $post['installments'] : [];
            $clientId = (int)($request->input('client_id') ?? 0);
            (new ProjectService())->updateBasic(
                $tenantId,
                $projectId,
                $clientId,
                $userId > 0 ? $userId : null,
                [
                    'name' => Security::sanitizeString((string)($request->input('name') ?? '')),
                    'description' => Security::sanitizeString((string)($request->input('description') ?? '')),
                    'status' => Security::sanitizeString((string)($request->input('status') ?? 'active')),
                    'start_date' => Security::sanitizeString((string)($request->input('start_date') ?? '')),
                    'due_date' => Security::sanitizeString((string)($request->input('due_date') ?? '')),
                    'contract_value' => Security::sanitizeString((string)($request->input('contract_value') ?? '0')),
                    'entry_amount' => Security::sanitizeString((string)($request->input('entry_amount') ?? '0')),
                    'payment_terms' => Security::sanitizeString((string)($request->input('payment_terms') ?? '')),
                    'installments_count' => (int)($request->input('installments_count') ?? 0),
                    'first_installment_date' => Security::sanitizeString((string)($request->input('first_installment_date') ?? '')),
                ]
            );
            $created = (new ProjectService())->ensureFinancialPlan(
                $tenantId,
                $projectId,
                $clientId,
                $userId > 0 ? $userId : null,
                [
                    'contract_value' => (string)($request->input('contract_value') ?? '0'),
                    'entry_amount' => (string)($request->input('entry_amount') ?? '0'),
                    'payment_terms' => Security::sanitizeString((string)($request->input('payment_terms') ?? '')),
                    'installments_count' => (int)($request->input('installments_count') ?? 0),
                    'first_installment_date' => (string)($request->input('first_installment_date') ?? ''),
                    'installments' => array_values(array_filter($installments, static fn($row): bool => is_array($row))),
                ]
            );
            Flash::set('ok', 'Projeto atualizado com sucesso.');
            if ($created) {
                Flash::set('ok', 'Projeto atualizado e financeiro gerado com sucesso.');
            }
        } catch (\Throwable $e) {
            error_log('[ProjectsModuleController] ' . $e->getMessage());
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }

        Response::redirect('/projetos');
    }

    public function destroy(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);
        $role = (string)Session::get('role', 'user');
        if (!(new ProjectAccessService())->canManage($role)) {
            Flash::set('error', 'Você não tem permissão para excluir projetos.');
            Response::redirect('/projetos');
            return;
        }

        $projectId = (int)($request->route('id', '0') ?? '0');

        try {
            (new ProjectService())->deleteBasic($tenantId, $projectId, $userId > 0 ? $userId : null);
            Flash::set('ok', 'Projeto excluído com sucesso.');
        } catch (\Throwable $e) {
            error_log('[ProjectsModuleController] ' . $e->getMessage());
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }

        Response::redirect('/projetos');
    }
}
