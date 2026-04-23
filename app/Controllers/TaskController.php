<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\Flash;
use App\Helpers\Security;
use App\Helpers\Session;
use App\Services\TaskService;

final class TaskController
{
    public function store(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);

        try {
            (new TaskService())->create(
                $tenantId,
                (int)($request->input('project_id') ?? 0),
                $userId > 0 ? $userId : null,
                [
                    'title' => Security::sanitizeString($request->input('title') ?? ''),
                    'description' => Security::sanitizeString($request->input('description') ?? ''),
                    'status' => Security::sanitizeString($request->input('status') ?? 'todo'),
                    'priority' => Security::sanitizeString($request->input('priority') ?? 'medium'),
                    'due_date' => (string)($request->input('due_date') ?? ''),
                    'task_kind' => Security::sanitizeString($request->input('task_kind') ?? 'in_scope'),
                    'billable_amount' => $request->input('billable_amount') ?? '0',
                ]
            );
            Flash::set('ok', 'Tarefa criada com sucesso.');
        } catch (\Throwable $e) {
            error_log('[TaskController] ' . $e->getMessage());
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }

        Response::redirect('/projects');
    }
}
