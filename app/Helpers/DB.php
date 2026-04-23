<?php
declare(strict_types=1);

namespace App\Helpers;

use PDO;

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }

        $cfg = Config::get('database');
        self::$pdo = new PDO(
            $cfg['dsn'],
            $cfg['user'],
            $cfg['pass'],
            $cfg['options']
        );
        return self::$pdo;
    }
}

