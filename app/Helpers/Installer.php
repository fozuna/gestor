<?php
declare(strict_types=1);

namespace App\Helpers;

use PDO;
use Throwable;

final class Installer
{
    public static function installed(): bool
    {
        return (bool)(self::status()['installed'] ?? false);
    }

    public static function status(): array
    {
        $lockExists = is_file(self::lockPath());
        $envPath = Path::base('.env');
        $envExists = is_file($envPath);
        $storageDir = Path::storage();
        $storageWritable = is_dir($storageDir) && is_writable($storageDir);
        $envConfigured = self::hasConfiguredEnvironment();

        $database = self::databaseStatus();
        $installed = $lockExists || ($envConfigured && (bool)$database['ok']);

        if ($installed && !$lockExists) {
            self::lockSilently();
            $lockExists = is_file(self::lockPath());
        }

        return [
            'installed' => $installed,
            'reason' => self::buildReason($lockExists, $envExists, $envConfigured, (bool)$database['ok']),
            'lock_exists' => $lockExists,
            'lock_path' => self::lockPath(),
            'env_exists' => $envExists,
            'env_configured' => $envConfigured,
            'storage_writable' => $storageWritable,
            'database' => $database,
        ];
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
        return Path::storage('installed.lock');
    }

    private static function hasConfiguredEnvironment(): bool
    {
        $envPath = Path::base('.env');
        if (!is_file($envPath)) {
            return false;
        }

        $dbHost = trim((string)(getenv('DB_HOST') ?: ''));
        $dbPort = trim((string)(getenv('DB_PORT') ?: ''));
        $dbName = trim((string)(getenv('DB_DATABASE') ?: ''));
        $dbUser = trim((string)(getenv('DB_USERNAME') ?: ''));

        return $dbHost !== '' && $dbPort !== '' && $dbName !== '' && $dbUser !== '';
    }

    private static function databaseStatus(): array
    {
        try {
            $cfg = Config::get('database');
            if (($cfg['dsn'] ?? '') === '' || ($cfg['user'] ?? '') === '') {
                return [
                    'ok' => false,
                    'connected' => false,
                    'required_tables' => ['users', 'tenants', 'memberships', 'settings'],
                    'existing_tables' => [],
                    'missing_tables' => ['users', 'tenants', 'memberships', 'settings'],
                    'error' => 'DSN ou usuário do banco ausente.',
                ];
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

            $missing = array_values(array_filter(
                $requiredTables,
                static fn(string $table): bool => !in_array($table, $existing, true)
            ));

            return [
                'ok' => $missing === [],
                'connected' => true,
                'required_tables' => $requiredTables,
                'existing_tables' => $existing,
                'missing_tables' => $missing,
                'error' => null,
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'connected' => false,
                'required_tables' => ['users', 'tenants', 'memberships', 'settings'],
                'existing_tables' => [],
                'missing_tables' => ['users', 'tenants', 'memberships', 'settings'],
                'error' => 'Falha ao conectar no banco ou consultar o schema.',
            ];
        }
    }

    private static function buildReason(bool $lockExists, bool $envExists, bool $envConfigured, bool $databaseOk): string
    {
        if ($lockExists) {
            return 'lock';
        }
        if (!$envExists) {
            return 'missing_env_file';
        }
        if (!$envConfigured) {
            return 'incomplete_env';
        }
        if (!$databaseOk) {
            return 'database_not_ready';
        }

        return 'database_autodetected';
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

