<?php
declare(strict_types=1);

namespace App;

use App\Core\Router;

final class App
{
    public static function run(): void
    {
        $router = Router::fromGlobals();
        require dirname(__DIR__) . '/routes/web.php';
        $router->dispatch();
    }
}

