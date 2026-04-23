<?php
declare(strict_types=1);

namespace App\Helpers;

final class CSRF
{
    public static function token(): string
    {
        Session::start();
        $t = Session::get('csrf_token');
        if (is_string($t) && $t !== '') {
            return $t;
        }
        $t = bin2hex(random_bytes(32));
        Session::set('csrf_token', $t);
        return $t;
    }

    public static function verify(?string $token): bool
    {
        Session::start();
        $s = Session::get('csrf_token');
        if (!is_string($s) || $s === '') {
            return false;
        }
        if (!is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($s, $token);
    }
}

