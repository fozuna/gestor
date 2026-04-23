<?php
declare(strict_types=1);

use App\Helpers\DB;

require dirname(__DIR__) . '/bootstrap/app.php';

$email = 'admin@traxter.com.br';
$password = '23082524';

$pdo = DB::pdo();
$st = $pdo->prepare('UPDATE users SET password_hash = :hash, is_active = 1 WHERE email = :email');
$st->execute([
    ':hash' => password_hash($password, PASSWORD_DEFAULT),
    ':email' => $email,
]);

echo 'UPDATED_ROWS=' . $st->rowCount() . PHP_EOL;
