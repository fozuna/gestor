<?php
declare(strict_types=1);

namespace App\Helpers;

final class Config
{
    private static array $cache = [];

    public static function get(string $file): array
    {
        if (!isset(self::$cache[$file])) {
            $path = Path::config($file . '.php');
            self::$cache[$file] = is_file($path) ? (require $path) : [];
        }
        return self::$cache[$file];
    }
}

