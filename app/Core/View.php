<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        $base = dirname(__DIR__, 2) . '/resources/views/';
        $file = $base . ltrim($view, '/') . '.php';
        if (!is_file($file)) {
            Response::html('View not found', 500);
            return;
        }

        extract($data, EXTR_SKIP);
        require $file;
    }
}

