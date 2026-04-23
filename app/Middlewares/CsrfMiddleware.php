<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\CSRF;

final class CsrfMiddleware
{
    public static function protect(callable $next): callable
    {
        return function (Request $request) use ($next): void {
            $m = $request->method();
            if ($m === 'POST') {
                $ok = CSRF::verify($request->input('_csrf'));
                if (!$ok) {
                    Response::html('CSRF inválido', 419);
                    return;
                }
            }
            $next($request);
        };
    }
}

