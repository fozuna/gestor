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
        $runtimeConfigSource = (string)(function_exists('runtime_env') ? runtime_env('APP_RUNTIME_CONFIG_SOURCE', '') : '');
        $runtimeConfigPath = $runtimeConfigSource !== '' ? Path::config($runtimeConfigSource) : '';
        $runtimeConfigFileExists = $runtimeConfigPath !== '' && is_file($runtimeConfigPath);
        $storageDir = Path::storage();
        $storageWritable = is_dir($storageDir) && is_writable($storageDir);
        $runtimeConfig = self::runtimeConfigurationStatus();
        $envConfigured = (bool)($runtimeConfig['configured'] ?? false);

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
            'runtime_config_source' => $runtimeConfigSource,
            'runtime_config_file_exists' => $runtimeConfigFileExists,
            'env_configured' => $envConfigured,
            'storage_writable' => $storageWritable,
            'runtime_config' => $runtimeConfig,
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

    private static function runtimeConfigurationStatus(): array
    {
        $runtimeSource = (string)(function_exists('runtime_env') ? runtime_env('APP_RUNTIME_CONFIG_SOURCE', '') : '');
        $env = static fn(string $key): string => trim((string)(function_exists('runtime_env')
            ? runtime_env($key, '')
            : (getenv($key) ?: '')));

        $dbHost = $env('DB_HOST');
        $dbPort = $env('DB_PORT');
        $dbName = $env('DB_DATABASE');
        $dbUser = $env('DB_USERNAME');

        $configured = $dbHost !== '' && $dbPort !== '' && $dbName !== '' && $dbUser !== '';
        $source = is_file(Path::base('.env')) ? '.env' : ($runtimeSource !== '' ? $runtimeSource : 'none');

        return [
            'configured' => $configured,
            'source' => $source,
            'db_host' => $dbHost !== '',
            'db_port' => $dbPort !== '',
            'db_database' => $dbName !== '',
            'db_username' => $dbUser !== '',
        ];
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
        $runtimeConfigSource = (string)(function_exists('runtime_env') ? runtime_env('APP_RUNTIME_CONFIG_SOURCE', '') : '');
        if (!$envExists && $runtimeConfigSource === '') {
            return 'missing_runtime_config';
        }
        if (!$envConfigured) {
            return 'incomplete_runtime_config';
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

