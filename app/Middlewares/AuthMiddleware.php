<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\Session;

final class AuthMiddleware
{
    public static function requireAuth(callable $next): callable
    {
        return function (Request $request) use ($next): void {
            Session::start();
            $uid = Session::get('user_id');
            if (!is_int($uid) && !ctype_digit((string)$uid)) {
                Response::redirect('/login');
                return;
            }
            $next($request);
        };
    }
}

