<?php
declare(strict_types=1);

namespace App\Helpers;

final class Path
{
    public static function base(string $append = ''): string
    {
        return self::join((string)(defined('APP_BASE_PATH') ? APP_BASE_PATH : dirname(__DIR__, 2)), $append);
    }

    public static function public(string $append = ''): string
    {
        return self::join((string)(defined('APP_PUBLIC_PATH') ? APP_PUBLIC_PATH : self::base('public')), $append);
    }

    public static function storage(string $append = ''): string
    {
        return self::join((string)(defined('APP_STORAGE_PATH') ? APP_STORAGE_PATH : self::base('storage')), $append);
    }

    public static function config(string $append = ''): string
    {
        return self::join((string)(defined('APP_CONFIG_PATH') ? APP_CONFIG_PATH : self::base('config')), $append);
    }

    private static function join(string $base, string $append = ''): string
    {
        $base = rtrim($base, '\\/');
        $append = ltrim($append, '\\/');
        if ($append === '') {
            return $base;
        }

        return $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $append);
    }
}
