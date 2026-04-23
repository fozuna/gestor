<?php
declare(strict_types=1);

namespace App\Helpers;

final class Flash
{
    public static function set(string $key, string $message): void
    {
        Session::start();
        $_SESSION['_flash'][$key] = $message;
    }

    public static function get(string $key): ?string
    {
        Session::start();
        if (!isset($_SESSION['_flash'][$key])) {
            return null;
        }
        $v = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return is_string($v) ? $v : null;
    }
}

