<?php
declare(strict_types=1);

$secure = (getenv('SESSION_SECURE') ?: 'false') === 'true';

return [
    'name' => getenv('SESSION_NAME') ?: 'traxter_session',
    'secure' => $secure,
    'samesite' => getenv('SESSION_SAMESITE') ?: 'Lax',
];

