<?php
declare(strict_types=1);

namespace App\Helpers;

use PDO;
use Throwable;

final class Installer
{
    public static function installed(): bool
    {
        if (is_file(self::lockPath())) {
            return true;
        }

        if (!self::hasConfiguredEnvironment()) {
            return false;
        }

        if (!self::databaseLooksInstalled()) {
            return false;
        }

        self::lockSilently();
        return true;
    }

    public static function lock(): void
    {
        $path = self::lockPath();
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, (string)time());
    }

    private static function lockPath(): string
    {
        return dirname(__DIR__, 2) . '/storage/installed.lock';
    }

    private static function hasConfiguredEnvironment(): bool
    {
        $envPath = dirname(__DIR__, 2) . '/.env';
        if (!is_file($envPath)) {
            return false;
        }

        $dbHost = trim((string)(getenv('DB_HOST') ?: ''));
        $dbPort = trim((string)(getenv('DB_PORT') ?: ''));
        $dbName = trim((string)(getenv('DB_DATABASE') ?: ''));
        $dbUser = trim((string)(getenv('DB_USERNAME') ?: ''));

        return $dbHost !== '' && $dbPort !== '' && $dbName !== '' && $dbUser !== '';
    }

    private static function databaseLooksInstalled(): bool
    {
        try {
            $cfg = Config::get('database');
            if (($cfg['dsn'] ?? '') === '' || ($cfg['user'] ?? '') === '') {
                return false;
            }

            $pdo = new PDO(
                (string)$cfg['dsn'],
                (string)($cfg['user'] ?? ''),
                (string)($cfg['pass'] ?? ''),
                $cfg['options'] ?? []
            );

            $requiredTables = ['users', 'tenants', 'memberships', 'settings'];
            $st = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()');
            $rows = $st ? $st->fetchAll(PDO::FETCH_COLUMN) : [];
            $existing = array_map('strval', is_array($rows) ? $rows : []);

            foreach ($requiredTables as $table) {
                if (!in_array($table, $existing, true)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private static function lockSilently(): void
    {
        try {
            self::lock();
        } catch (Throwable) {
            // Ambientes compartilhados podem ter permissões restritas em storage.
            // Mesmo sem conseguir gravar o lock, se o banco já estiver válido
            // consideramos a aplicação instalada para evitar redirecionamento indevido.
        }
    }
}

