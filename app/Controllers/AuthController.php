<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Helpers\CSRF;
use App\Helpers\DiagnosticLogger;
use App\Helpers\Flash;
use App\Helpers\Installer;
use App\Helpers\Security;
use App\Helpers\Session;
use App\Services\AuthService;

final class AuthController
{
    public function loginView(Request $request): void
    {
        $installerStatus = Installer::status();
        if (!(bool)($installerStatus['installed'] ?? false)) {
            DiagnosticLogger::log('install-redirect', [
                'target' => '/install',
                'source' => 'AuthController::loginView',
                'installer_status' => $installerStatus,
                'context' => DiagnosticLogger::requestContext(),
                'trace' => DiagnosticLogger::trace(),
            ]);
            Response::redirect('/install');
            return;
        }

        Session::start();
        if (Session::get('user_id')) {
            if (Session::get('tenant_id')) {
                Response::redirect('/dashboard');
                return;
            }
            Response::redirect('/workspaces');
            return;
        }

        View::render('auth/login', [
            'csrf' => CSRF::token(),
            'error' => Flash::get('error'),
        ]);
    }

    public function login(Request $request): void
    {
        $email = Security::sanitizeEmail($request->input('email'));
        $password = (string)($request->input('password') ?? '');

        $ok = (new AuthService())->login($email, $password);
        if (!$ok) {
            Flash::set('error', 'Credenciais inválidas.');
            Response::redirect('/login');
            return;
        }

        if (Session::get('tenant_id')) {
            Response::redirect('/dashboard');
            return;
        }

        Response::redirect('/workspaces');
    }

    public function logout(Request $request): void
    {
        (new AuthService())->logout();
        Response::redirect('/login');
    }
}

