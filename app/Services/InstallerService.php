<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Installer;
use PDO;
use RuntimeException;

final class InstallerService
{
    public const DEFAULT_ADMIN_EMAIL = 'admin@traxter.com.br';
    public const DEFAULT_ADMIN_PASSWORD = 'Ab23082524@';
    public const DEFAULT_TENANT_NAME = 'TRAXTER';

    public function defaults(): array
    {
        return [
            'app_url' => 'http://localhost:8000',
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_name' => 'traxter',
            'db_user' => 'root',
            'db_pass' => '',
            'session_secure' => 'false',
        ];
    }

    public function writeEnv(array $env): void
    {
        $env = $this->normalizeEnv($env);
        $appKey = bin2hex(random_bytes(16));
        $lines = [
            'APP_NAME="TRAXTER CRM"',
            'APP_ENV=local',
            'APP_URL=' . ($env['app_url'] ?? 'http://localhost'),
            'APP_KEY=' . $appKey,
            '',
            'DB_HOST=' . $env['db_host'],
            'DB_PORT=' . $env['db_port'],
            'DB_DATABASE=' . $env['db_name'],
            'DB_USERNAME=' . $env['db_user'],
            'DB_PASSWORD=' . $env['db_pass'],
            '',
            'SESSION_NAME=traxter_session',
            'SESSION_SECURE=' . (($env['session_secure'] ?? 'false') === 'true' ? 'true' : 'false'),
            'SESSION_SAMESITE=Lax',
            '',
        ];
        $path = dirname(__DIR__, 2) . '/.env';
        file_put_contents($path, implode("\n", $lines));
    }

    public function install(
        array $env,
        string $tenantName,
        string $adminEmail,
        string $adminPassword,
        bool $writeEnv = true,
        bool $lock = false
    ): array {
        $env = $this->normalizeEnv($env);
        $this->assertRequiredInputs($env, $tenantName, $adminEmail, $adminPassword);

        $server = $this->connectServer($env);
        $this->createDatabase($server, $env['db_name']);

        $pdo = $this->connect($env);
        $this->runSchema($pdo);
        $seed = $this->seed($pdo, $tenantName, $adminEmail, $adminPassword);
        $validation = $this->validateDatabase($pdo);
        $adminValidated = $this->validateAdminCredentials($pdo, $adminEmail, $adminPassword);

        if ($writeEnv) {
            $this->writeEnv($env);
        }

        if ($lock) {
            $this->finish();
        }

        return [
            'database' => $env['db_name'],
            'validation' => $validation,
            'admin_validated' => $adminValidated,
            'seed' => $seed,
        ];
    }

    public function connectServer(array $env): PDO
    {
        $env = $this->normalizeEnv($env);
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=utf8mb4',
            $env['db_host'],
            $env['db_port']
        );
        return new PDO($dsn, $env['db_user'], $env['db_pass'], $this->pdoOptions());
    }

    public function connect(array $env): PDO
    {
        $env = $this->normalizeEnv($env);
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['db_host'],
            $env['db_port'],
            $env['db_name']
        );
        return new PDO($dsn, $env['db_user'], $env['db_pass'], $this->pdoOptions());
    }

    public function createDatabase(PDO $pdo, string $databaseName): void
    {
        $name = $this->quoteIdentifier($databaseName);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $name . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function runSchema(PDO $pdo): void
    {
        $schemaPath = dirname(__DIR__, 2) . '/install/schema.sql';
        $sql = (string)file_get_contents($schemaPath);
        foreach ($this->splitSql($sql) as $stmt) {
            $s = trim($stmt);
            if ($s === '') {
                continue;
            }
            $pdo->exec($s);
        }
    }

    public function seed(PDO $pdo, string $tenantName, string $adminEmail, string $adminPassword): array
    {
        $pdo->beginTransaction();

        try {
            $tenantId = $this->ensureTenant($pdo, $tenantName);
            $userId = $this->ensureAdminUser($pdo, $adminEmail, $adminPassword);
            $membershipCreated = $this->ensureAdminMembership($pdo, $tenantId, $userId);
            $this->ensureSettings($pdo, $tenantId, $tenantName);

            $pdo->commit();

            return [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'membership_created' => $membershipCreated,
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function validateDatabase(PDO $pdo): array
    {
        $requiredTables = [
            'users',
            'tenants',
            'memberships',
            'settings',
            'clients',
            'client_interactions',
            'contract_templates',
            'quotes',
            'quote_items',
            'projects',
            'contracts',
            'project_timelines',
            'tasks',
            'task_comments',
            'invoices',
            'payments',
            'financial_entries',
            'action_logs',
        ];

        $st = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()');
        $rows = $st->fetchAll(PDO::FETCH_COLUMN);
        $existing = array_map('strval', is_array($rows) ? $rows : []);
        $missing = array_values(array_diff($requiredTables, $existing));

        $constraintsCount = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE()"
        )->fetchColumn();

        return [
            'required_tables' => $requiredTables,
            'existing_tables' => $existing,
            'missing_tables' => $missing,
            'foreign_keys_count' => $constraintsCount,
            'ok' => $missing === [] && $constraintsCount > 0,
        ];
    }

    public function validateAdminCredentials(PDO $pdo, string $adminEmail, string $adminPassword): bool
    {
        $st = $pdo->prepare('SELECT password_hash FROM users WHERE email = :email LIMIT 1');
        $st->execute([':email' => $adminEmail]);
        $hash = $st->fetchColumn();
        return is_string($hash) && $hash !== '' && password_verify($adminPassword, $hash);
    }

    public function finish(): void
    {
        Installer::lock();
    }

    private function assertRequiredInputs(array $env, string $tenantName, string $adminEmail, string $adminPassword): void
    {
        if ($env['db_host'] === '' || $env['db_name'] === '' || $env['db_user'] === '') {
            throw new RuntimeException('As credenciais de banco estão incompletas.');
        }

        if ($tenantName === '' || $adminEmail === '' || strlen($adminPassword) < 8) {
            throw new RuntimeException('Tenant, e-mail admin e senha válida são obrigatórios.');
        }
    }

    private function ensureTenant(PDO $pdo, string $tenantName): int
    {
        $find = $pdo->prepare('SELECT id FROM tenants WHERE name = :name LIMIT 1');
        $find->execute([':name' => $tenantName]);
        $tenantId = $find->fetchColumn();

        if ($tenantId !== false) {
            return (int)$tenantId;
        }

        $slug = $this->slugify($tenantName);
        $insert = $pdo->prepare('INSERT INTO tenants (name, slug) VALUES (:name, :slug)');
        $insert->execute([
            ':name' => $tenantName,
            ':slug' => $slug !== '' ? $slug : null,
        ]);

        return (int)$pdo->lastInsertId();
    }

    private function ensureAdminUser(PDO $pdo, string $adminEmail, string $adminPassword): int
    {
        $find = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $find->execute([':email' => $adminEmail]);
        $userId = $find->fetchColumn();

        if ($userId !== false) {
            return (int)$userId;
        }

        $insert = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, is_active) VALUES (:name, :email, :hash, 1)'
        );
        $insert->execute([
            ':name' => 'Administrador TRAXTER',
            ':email' => $adminEmail,
            ':hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
        ]);

        return (int)$pdo->lastInsertId();
    }

    private function ensureAdminMembership(PDO $pdo, int $tenantId, int $userId): bool
    {
        $exists = $pdo->prepare('SELECT id FROM memberships WHERE tenant_id = :tenant_id AND user_id = :user_id LIMIT 1');
        $exists->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
        ]);
        $membershipId = $exists->fetchColumn();

        if ($membershipId !== false) {
            $update = $pdo->prepare('UPDATE memberships SET role = :role WHERE id = :id');
            $update->execute([
                ':role' => 'admin',
                ':id' => (int)$membershipId,
            ]);
            return false;
        }

        $insert = $pdo->prepare(
            'INSERT INTO memberships (tenant_id, user_id, role) VALUES (:tenant_id, :user_id, :role)'
        );
        $insert->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
            ':role' => 'admin',
        ]);

        return true;
    }

    private function ensureSettings(PDO $pdo, int $tenantId, string $tenantName): void
    {
        $st = $pdo->prepare(
            'INSERT INTO settings (tenant_id, company_name, primary_color, secondary_color, accent_color)
             VALUES (:tenant_id, :company_name, :primary, :secondary, :accent)
             ON DUPLICATE KEY UPDATE
               company_name = VALUES(company_name),
               primary_color = VALUES(primary_color),
               secondary_color = VALUES(secondary_color),
               accent_color = VALUES(accent_color)'
        );
        $st->execute([
            ':tenant_id' => $tenantId,
            ':company_name' => $tenantName,
            ':primary' => '#FE5516',
            ':secondary' => '#F5F5DC',
            ':accent' => '#E8D9BB',
        ]);
    }

    private function normalizeEnv(array $env): array
    {
        $defaults = $this->defaults();
        $normalized = array_merge($defaults, $env);

        $normalized['db_name'] = (string)($normalized['db_name'] ?? $normalized['db_database'] ?? $defaults['db_name']);
        $normalized['db_host'] = (string)$normalized['db_host'];
        $normalized['db_port'] = (string)$normalized['db_port'];
        $normalized['db_user'] = (string)($normalized['db_user'] ?? $defaults['db_user']);
        $normalized['db_pass'] = (string)($normalized['db_pass'] ?? '');
        $normalized['app_url'] = (string)($normalized['app_url'] ?? $defaults['app_url']);
        $normalized['session_secure'] = ($normalized['session_secure'] ?? 'false') === true
            ? 'true'
            : (string)$normalized['session_secure'];

        return $normalized;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function slugify(string $value): string
    {
        $value = trim(strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    private function splitSql(string $sql): array
    {
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        $out = [];
        $buf = '';
        $inS = false;
        $inD = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $nx = $i + 1 < $len ? $sql[$i + 1] : '';

            if (!$inS && !$inD) {
                if ($ch === '-' && $nx === '-') {
                    while ($i < $len && $sql[$i] !== "\n") {
                        $i++;
                    }
                    continue;
                }
                if ($ch === '#') {
                    while ($i < $len && $sql[$i] !== "\n") {
                        $i++;
                    }
                    continue;
                }
            }

            if ($ch === "'" && !$inD) {
                $inS = !$inS;
                $buf .= $ch;
                continue;
            }
            if ($ch === '"' && !$inS) {
                $inD = !$inD;
                $buf .= $ch;
                continue;
            }

            if ($ch === ';' && !$inS && !$inD) {
                $out[] = $buf;
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }

        if (trim($buf) !== '') {
            $out[] = $buf;
        }
        return $out;
    }
}

