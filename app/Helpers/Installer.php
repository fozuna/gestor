<?php
declare(strict_types=1);

namespace App\Helpers;

final class Installer
{
    public static function installed(): bool
    {
        return is_file(dirname(__DIR__, 2) . '/storage/installed.lock');
    }

    public static function lock(): void
    {
        $path = dirname(__DIR__, 2) . '/storage/installed.lock';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, (string)time());
    }
}

