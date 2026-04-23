<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\Session;

final class TenantMiddleware
{
    public static function requireTenant(callable $next): callable
    {
        return function (Request $request) use ($next): void {
            Session::start();
            $tenantId = Session::get('tenant_id');
            if (!is_int($tenantId) && !ctype_digit((string)$tenantId)) {
                Response::redirect('/workspaces');
                return;
            }
            $next($request);
        };
    }
}

