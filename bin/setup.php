<?php
declare(strict_types=1);

use App\Services\InstallerService;

require dirname(__DIR__) . '/bootstrap/app.php';

$service = new InstallerService();
$defaults = $service->defaults();
$options = getopt('', [
    'app-url::',
    'db-host::',
    'db-port::',
    'db-name::',
    'db-user::',
    'db-pass::',
    'tenant-name::',
    'admin-email::',
    'admin-password::',
    'session-secure::',
    'no-env',
    'no-lock',
]);

$env = [
    'app_url' => (string)($options['app-url'] ?? getenv('APP_URL') ?: $defaults['app_url']),
    'db_host' => (string)($options['db-host'] ?? getenv('DB_HOST') ?: $defaults['db_host']),
    'db_port' => (string)($options['db-port'] ?? getenv('DB_PORT') ?: $defaults['db_port']),
    'db_name' => (string)($options['db-name'] ?? getenv('DB_DATABASE') ?: $defaults['db_name']),
    'db_user' => (string)($options['db-user'] ?? getenv('DB_USERNAME') ?: $defaults['db_user']),
    'db_pass' => (string)($options['db-pass'] ?? getenv('DB_PASSWORD') ?: $defaults['db_pass']),
    'session_secure' => (string)($options['session-secure'] ?? getenv('SESSION_SECURE') ?: $defaults['session_secure']),
];

$tenantName = (string)($options['tenant-name'] ?? InstallerService::DEFAULT_TENANT_NAME);
$adminEmail = (string)($options['admin-email'] ?? InstallerService::DEFAULT_ADMIN_EMAIL);
$adminPassword = (string)($options['admin-password'] ?? InstallerService::DEFAULT_ADMIN_PASSWORD);
$writeEnv = !array_key_exists('no-env', $options);
$lock = !array_key_exists('no-lock', $options);

try {
    $result = $service->install($env, $tenantName, $adminEmail, $adminPassword, $writeEnv, $lock);
    $validation = $result['validation'];

    echo 'TRAXTER CRM setup concluído.' . PHP_EOL;
    echo 'Banco: ' . $result['database'] . PHP_EOL;
    echo 'Tenant ID: ' . $result['seed']['tenant_id'] . PHP_EOL;
    echo 'Admin ID: ' . $result['seed']['user_id'] . PHP_EOL;
    echo 'Membership criada nesta execução: ' . ($result['seed']['membership_created'] ? 'sim' : 'não') . PHP_EOL;
    echo 'Tabelas obrigatórias ausentes: ' . count($validation['missing_tables']) . PHP_EOL;
    echo 'Foreign keys detectadas: ' . $validation['foreign_keys_count'] . PHP_EOL;
    echo 'Credenciais do admin validadas: ' . ($result['admin_validated'] ? 'sim' : 'não') . PHP_EOL;

    if ($validation['missing_tables'] !== []) {
        echo 'Tabelas faltantes: ' . implode(', ', $validation['missing_tables']) . PHP_EOL;
        exit(1);
    }

    if (!$result['admin_validated']) {
        exit(1);
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Falha no setup: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
