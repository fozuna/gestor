<?php
declare(strict_types=1);

use App\Helpers\DB;

require dirname(__DIR__) . '/bootstrap/app.php';

$pdo = DB::pdo();
$st = $pdo->prepare('SELECT id, email, is_active, password_hash FROM users WHERE email = :email LIMIT 1');
$st->execute([':email' => 'admin@traxter.com.br']);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!is_array($user)) {
    echo "ADMIN_NOT_FOUND\n";
    exit(0);
}

echo 'ADMIN_FOUND id=' . (int)$user['id'] . ' active=' . (int)$user['is_active'] . PHP_EOL;
echo 'PASS_23082524=' . (password_verify('23082524', (string)$user['password_hash']) ? 'YES' : 'NO') . PHP_EOL;
echo 'PASS_Ab23082524@=' . (password_verify('Ab23082524@', (string)$user['password_hash']) ? 'YES' : 'NO') . PHP_EOL;
