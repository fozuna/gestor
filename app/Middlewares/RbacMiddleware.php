<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\Session;

final class RbacMiddleware
{
    public static function requireRole(array $roles, callable $next): callable
    {
        return function (Request $request) use ($roles, $next): void {
            Session::start();
            $role = Session::get('role');
            if (!is_string($role) || !in_array($role, $roles, true)) {
                Response::html('Acesso negado', 403);
                return;
            }
            $next($request);
        };
    }
}

