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
use App\Repositories\UserRepository;
use App\Services\ProjectService;
use App\Services\TaskAccessService;
use App\Services\TaskService;

final class TasksModuleController
{
    public function index(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $role = (string)Session::get('role', 'user');
        if (!(new TaskAccessService())->canView($role)) {
            Response::html('Acesso negado.', 403);
            return;
        }

        $filters = [
            'status' => Security::sanitizeString((string)($request->query('status', '') ?? '')),
            'priority' => Security::sanitizeString((string)($request->query('priority', '') ?? '')),
            'assignee' => Security::sanitizeString((string)($request->query('assignee', '') ?? '')),
            'client' => Security::sanitizeString((string)($request->query('client', '') ?? '')),
            'sort' => Security::sanitizeString((string)($request->query('sort', 'created_at') ?? 'created_at')),
            'page' => max(1, (int)($request->query('page', '1') ?? '1')),
            'per_page' => 10,
        ];

        $result = (new TaskService())->paginatedList($tenantId, $filters);
        $projects = (new ProjectService())->optionsByTenant($tenantId);
        $clients = (new ClientService())->optionsByTenant($tenantId);
        $users = (new UserRepository())->listByTenant($tenantId);
        $editTask = null;
        $editId = (int)($request->query('edit', '0') ?? '0');
        if ($editId > 0) {
            $editTask = (new TaskService())->detail($tenantId, $editId);
        }

        View::render('tasks/index', [
            'tasks' => $result['items'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
            'board' => (new TaskService())->boardByTenant($tenantId, (string)$filters['client']),
            'projects' => $projects,
            'clients' => $clients,
            'users' => $users,
            'editTask' => $editTask,
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
        if (!(new TaskAccessService())->canView($role)) {
            Response::html('Acesso negado.', 403);
            return;
        }

        $taskId = (int)($request->route('id', '0') ?? '0');
        $task = (new TaskService())->detail($tenantId, $taskId);
        if ($task === null) {
            Response::html('Tarefa não encontrada.', 404);
            return;
        }

        View::render('tasks/show', [
            'task' => $task,
            'tenantName' => (string)Session::get('tenant_name', 'TRAXTER'),
            'role' => (string)Session::get('role', 'user'),
            'ok' => Flash::get('ok'),
            'error' => Flash::get('error'),
        ]);
    }

    public function store(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);
        $role = (string)Session::get('role', 'user');
        if (!(new TaskAccessService())->canManage($role)) {
            Flash::set('error', 'Você não tem permissão para criar tarefas.');
            Response::redirect($this->redirectTarget($request));
            return;
        }

        try {
            $projectId = (int)($request->input('project_id') ?? 0);
            (new TaskService())->create($tenantId, $projectId, $userId > 0 ? $userId : null, $this->payload($request));
            Flash::set('ok', 'Tarefa criada com sucesso.');
        } catch (\Throwable $e) {
            error_log('[TasksModuleController] ' . $e->getMessage());
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }

        Response::redirect($this->redirectTarget($request));
    }

    public function update(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);
        $role = (string)Session::get('role', 'user');
        if (!(new TaskAccessService())->canManage($role)) {
            Flash::set('error', 'Você não tem permissão para editar tarefas.');
            Response::redirect($this->redirectTarget($request));
            return;
        }

        $taskId = (int)($request->route('id', '0') ?? '0');

        try {
            (new TaskService())->update($tenantId, $taskId, $userId > 0 ? $userId : null, $this->payload($request));
            Flash::set('ok', 'Tarefa atualizada com sucesso.');
        } catch (\Throwable $e) {
            error_log('[TasksModuleController] ' . $e->getMessage());
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }

        Response::redirect($this->redirectTarget($request));
    }

    public function destroy(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);
        $role = (string)Session::get('role', 'user');
        if (!(new TaskAccessService())->canManage($role)) {
            Flash::set('error', 'Você não tem permissão para excluir tarefas.');
            Response::redirect($this->redirectTarget($request));
            return;
        }

        $taskId = (int)($request->route('id', '0') ?? '0');

        try {
            (new TaskService())->delete($tenantId, $taskId, $userId > 0 ? $userId : null);
            Flash::set('ok', 'Tarefa excluída com sucesso.');
        } catch (\Throwable $e) {
            error_log('[TasksModuleController] ' . $e->getMessage());
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }

        Response::redirect($this->redirectTarget($request));
    }

    public function move(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);
        $role = (string)Session::get('role', 'user');
        if (!(new TaskAccessService())->canManage($role)) {
            Response::json(['success' => false, 'message' => 'Você não tem permissão para mover tarefas.'], 403);
            return;
        }

        $taskId = (int)($request->route('id', '0') ?? '0');

        try {
            $orderedIds = $request->all()['ordered_ids'] ?? [];
            $ids = is_array($orderedIds) ? $orderedIds : [];
            (new TaskService())->move(
                $tenantId,
                $taskId,
                $userId > 0 ? $userId : null,
                Security::sanitizeString((string)($request->input('status') ?? '')),
                $ids
            );
            Response::json(['success' => true, 'message' => 'Tarefa movida com sucesso.']);
        } catch (\Throwable $e) {
            error_log('[TasksModuleController] ' . $e->getMessage());
            Response::json(['success' => false, 'message' => Security::sanitizeString($e->getMessage())], 422);
        }
    }

    private function payload(Request $request): array
    {
        return [
            'project_id' => (int)($request->input('project_id') ?? 0),
            'title' => Security::sanitizeString((string)($request->input('title') ?? '')),
            'description' => Security::sanitizeString((string)($request->input('description') ?? '')),
            'status' => Security::sanitizeString((string)($request->input('status') ?? 'todo')),
            'priority' => Security::sanitizeString((string)($request->input('priority') ?? 'medium')),
            'task_kind' => Security::sanitizeString((string)($request->input('task_kind') ?? 'in_scope')),
            'billable_amount' => Security::sanitizeString((string)($request->input('billable_amount') ?? '0')),
            'assignee_user_id' => (int)($request->input('assignee_user_id') ?? 0),
            'due_date' => Security::sanitizeString((string)($request->input('due_date') ?? '')),
        ];
    }

    private function redirectTarget(Request $request): string
    {
        $returnTo = Security::sanitizeString((string)($request->input('return_to') ?? ''));
        return $returnTo !== '' ? $returnTo : '/tarefas';
    }
}
