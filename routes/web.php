<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\BudgetsModuleController;
use App\Controllers\ClientController;
use App\Controllers\DashboardController;
use App\Controllers\FinanceController;
use App\Controllers\InstallController;
use App\Controllers\ProjectController;
use App\Controllers\ProjectsModuleController;
use App\Controllers\TaskController;
use App\Controllers\TasksModuleController;
use App\Controllers\WorkspaceController;
use App\Core\Request;
use App\Core\Response;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;
use App\Middlewares\TenantMiddleware;

$router->get('/', function (Request $request): void {
    Response::redirect('/dashboard');
});

$router->get('/install', [new InstallController(), 'index']);
$router->post('/install', CsrfMiddleware::protect([new InstallController(), 'install']));

$router->get('/login', [new AuthController(), 'loginView']);
$router->post('/login', CsrfMiddleware::protect([new AuthController(), 'login']));
$router->post('/logout', CsrfMiddleware::protect(AuthMiddleware::requireAuth([new AuthController(), 'logout'])));

$router->get('/workspaces', AuthMiddleware::requireAuth([new WorkspaceController(), 'index']));
$router->post('/workspaces/select', CsrfMiddleware::protect(AuthMiddleware::requireAuth([new WorkspaceController(), 'select'])));

$router->get('/dashboard', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new DashboardController(), 'index'])));
$router->get('/clients', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ClientController(), 'index'])));
$router->get('/clients/profile', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ClientController(), 'profile'])));
$router->get('/clients/profile/tasks-pdf', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ClientController(), 'exportTasksPdf'])));
$router->post('/clients', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ClientController(), 'store']))));
$router->post('/clients/{id}/atualizar', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ClientController(), 'update']))));
$router->post('/clients/{id}/excluir', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ClientController(), 'destroy']))));
$router->get('/projetos', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ProjectsModuleController(), 'index'])));
$router->post('/projetos', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ProjectsModuleController(), 'store']))));
$router->get('/projetos/{id}', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ProjectsModuleController(), 'show'])));
$router->post('/projetos/{id}/atualizar', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ProjectsModuleController(), 'update']))));
$router->post('/projetos/{id}/excluir', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ProjectsModuleController(), 'destroy']))));
$router->get('/orcamentos', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new BudgetsModuleController(), 'index'])));
$router->post('/orcamentos', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new BudgetsModuleController(), 'store']))));
$router->post('/orcamentos/{id}/atualizar', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new BudgetsModuleController(), 'update']))));
$router->post('/orcamentos/{id}/status', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new BudgetsModuleController(), 'changeStatus']))));
$router->get('/orcamentos/{id}/proposta', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new BudgetsModuleController(), 'proposal'])));
$router->get('/orcamentos/{id}/proposta/txt', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new BudgetsModuleController(), 'exportProposalText'])));
$router->get('/orcamentos/{id}/proposta/pdf', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new BudgetsModuleController(), 'exportProposalPdf'])));
$router->get('/tarefas', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new TasksModuleController(), 'index'])));
$router->post('/tarefas', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new TasksModuleController(), 'store']))));
$router->get('/tarefas/{id}', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new TasksModuleController(), 'show'])));
$router->post('/tarefas/{id}/atualizar', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new TasksModuleController(), 'update']))));
$router->post('/tarefas/{id}/excluir', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new TasksModuleController(), 'destroy']))));
$router->post('/tarefas/{id}/mover', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new TasksModuleController(), 'move']))));
$router->get('/projects', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ProjectController(), 'index'])));
$router->get('/projects/show', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ProjectController(), 'show'])));
$router->post('/projects', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new ProjectController(), 'store']))));
$router->post('/tasks', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new TaskController(), 'store']))));
$router->get('/finance', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new FinanceController(), 'index'])));
$router->get('/finance/payments/{id}/receipt', AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new FinanceController(), 'downloadReceipt'])));
$router->post('/finance/installments/plan', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new FinanceController(), 'createPlan']))));
$router->post('/finance/installments/pay', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new FinanceController(), 'settle']))));
$router->post('/finance/installments/anticipation/simulate', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new FinanceController(), 'simulateAnticipation']))));
$router->post('/finance/installments/anticipation/process', CsrfMiddleware::protect(AuthMiddleware::requireAuth(TenantMiddleware::requireTenant([new FinanceController(), 'processAnticipation']))));

