<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Helpers\CSRF;
use App\Helpers\Flash;
use App\Helpers\Installer;
use App\Helpers\Security;
use App\Services\InstallerService;

final class InstallController
{
    public function index(Request $request): void
    {
        if (Installer::installed()) {
            Response::redirect('/login');
            return;
        }

        $service = new InstallerService();

        View::render('install/index', [
            'csrf' => CSRF::token(),
            'error' => Flash::get('error'),
            'ok' => Flash::get('ok'),
            'defaults' => $service->defaults(),
            'defaultTenantName' => InstallerService::DEFAULT_TENANT_NAME,
            'defaultAdminEmail' => InstallerService::DEFAULT_ADMIN_EMAIL,
        ]);
    }

    public function install(Request $request): void
    {
        if (Installer::installed()) {
            Response::redirect('/login');
            return;
        }

        $env = [
            'app_url' => Security::sanitizeString($request->input('app_url') ?? 'http://localhost:8000'),
            'db_host' => Security::sanitizeString($request->input('db_host') ?? '127.0.0.1'),
            'db_port' => Security::sanitizeString($request->input('db_port') ?? '3306'),
            'db_name' => Security::sanitizeString($request->input('db_name') ?? 'traxter'),
            'db_user' => Security::sanitizeString($request->input('db_user') ?? 'root'),
            'db_pass' => (string)($request->input('db_pass') ?? ''),
            'session_secure' => Security::sanitizeString($request->input('session_secure') ?? 'false'),
        ];

        $tenantName = Security::sanitizeString($request->input('tenant_name') ?? InstallerService::DEFAULT_TENANT_NAME);
        $adminEmail = Security::sanitizeEmail($request->input('admin_email') ?? InstallerService::DEFAULT_ADMIN_EMAIL);
        $adminPass = (string)($request->input('admin_password') ?? InstallerService::DEFAULT_ADMIN_PASSWORD);

        if ($env['db_host'] === '' || $env['db_name'] === '' || $env['db_user'] === '' || $adminEmail === '' || strlen($adminPass) < 8) {
            Flash::set('error', 'Preencha os campos obrigatórios (senha mínima 8 caracteres).');
            Response::redirect('/install');
            return;
        }

        try {
            $svc = new InstallerService();
            $svc->install($env, $tenantName, $adminEmail, $adminPass, true, true);
            Flash::set('ok', 'Instalação concluída. Faça login.');
            Response::redirect('/login');
        } catch (\Throwable $e) {
            Flash::set('error', 'Falha na instalação: ' . Security::sanitizeString($e->getMessage()));
            Response::redirect('/install');
        }
    }
}

