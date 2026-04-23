<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Helpers\Flash;
use App\Helpers\Security;
use App\Helpers\Session;
use App\Services\BudgetService;
use App\Services\ClientService;
use App\Services\ProjectAccessService;

final class BudgetsModuleController
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

        $service = new BudgetService();
        $budgets = $service->paginatedList($tenantId, $filters);
        $editBudget = null;
        $editId = (int)($request->query('edit', '0') ?? '0');
        if ($editId > 0) {
            $editBudget = $service->detail($tenantId, $editId);
        }

        View::render('budgets/module', [
            'budgets' => $budgets['items'],
            'pagination' => $budgets['pagination'],
            'filters' => $filters,
            'clients' => (new ClientService())->optionsByTenant($tenantId),
            'editBudget' => $editBudget,
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
            Flash::set('error', 'Você não tem permissão para criar orçamentos.');
            Response::redirect('/orcamentos');
            return;
        }

        try {
            (new BudgetService())->create($tenantId, $userId > 0 ? $userId : null, $request->all());
            Flash::set('ok', 'Orçamento criado com sucesso.');
        } catch (\Throwable $e) {
            error_log('[BudgetsModuleController] ' . $e->getMessage());
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }
        Response::redirect('/orcamentos');
    }

    public function update(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);
        $role = (string)Session::get('role', 'user');
        if (!(new ProjectAccessService())->canManage($role)) {
            Flash::set('error', 'Você não tem permissão para editar orçamentos.');
            Response::redirect('/orcamentos');
            return;
        }

        $budgetId = (int)($request->route('id', '0') ?? '0');
        try {
            (new BudgetService())->update($tenantId, $budgetId, $userId > 0 ? $userId : null, $request->all());
            Flash::set('ok', 'Orçamento atualizado com sucesso.');
        } catch (\Throwable $e) {
            error_log('[BudgetsModuleController] ' . $e->getMessage());
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }
        Response::redirect('/orcamentos');
    }

    public function changeStatus(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);
        $role = (string)Session::get('role', 'user');
        if (!(new ProjectAccessService())->canManage($role)) {
            Flash::set('error', 'Você não tem permissão para alterar status de orçamentos.');
            Response::redirect('/orcamentos');
            return;
        }

        $budgetId = (int)($request->route('id', '0') ?? '0');
        $status = Security::sanitizeString((string)($request->input('status') ?? ''));
        try {
            (new BudgetService())->changeStatus($tenantId, $budgetId, $status, $userId > 0 ? $userId : null);
            Flash::set('ok', $status === 'aprovado'
                ? 'Orçamento aprovado e projeto criado automaticamente.'
                : 'Status do orçamento atualizado.');
        } catch (\Throwable $e) {
            error_log('[BudgetsModuleController] ' . $e->getMessage());
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }
        Response::redirect('/orcamentos');
    }

    public function proposal(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $role = (string)Session::get('role', 'user');
        if (!(new ProjectAccessService())->canView($role)) {
            Response::html('Acesso negado.', 403);
            return;
        }

        $budgetId = (int)($request->route('id', '0') ?? '0');
        $presentation = (string)($request->query('apresentacao', '') ?? '');
        try {
            $preview = (new BudgetService())->buildProposalPreview(
                $tenantId,
                $budgetId,
                (string)Session::get('tenant_name', 'TRAXTER'),
                $presentation
            );
            View::render('budgets/proposal', [
                'preview' => $preview,
                'presentation' => $presentation,
                'tenantName' => (string)Session::get('tenant_name', 'TRAXTER'),
                'role' => $role,
            ]);
        } catch (\Throwable $e) {
            Flash::set('error', Security::sanitizeString($e->getMessage()));
            Response::redirect('/orcamentos');
        }
    }

    public function exportProposalText(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $budgetId = (int)($request->route('id', '0') ?? '0');
        $presentation = (string)($request->query('apresentacao', '') ?? '');
        try {
            $file = (new BudgetService())->exportProposalText(
                $tenantId,
                $budgetId,
                (string)Session::get('tenant_name', 'TRAXTER'),
                $presentation
            );
            header('Content-Type: text/plain; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
            echo $file['content'];
            exit;
        } catch (\Throwable $e) {
            Flash::set('error', Security::sanitizeString($e->getMessage()));
            Response::redirect('/orcamentos');
        }
    }

    public function exportProposalPdf(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $budgetId = (int)($request->route('id', '0') ?? '0');
        $presentation = (string)($request->query('apresentacao', '') ?? '');
        try {
            $file = (new BudgetService())->exportProposalPdf(
                $tenantId,
                $budgetId,
                (string)Session::get('tenant_name', 'TRAXTER'),
                $presentation
            );
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
            header('Content-Length: ' . strlen((string)$file['content']));
            echo $file['content'];
            exit;
        } catch (\Throwable $e) {
            Flash::set('error', Security::sanitizeString($e->getMessage()));
            Response::redirect('/orcamentos');
        }
    }
}

