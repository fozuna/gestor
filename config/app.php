<?php
declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'TRAXTER CRM',
    'env' => getenv('APP_ENV') ?: 'production',
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'key' => getenv('APP_KEY') ?: '',
];

