<?php
declare(strict_types=1);

namespace App\Core;

use App\Helpers\DiagnosticLogger;

final class Response
{
    public static function redirect(string $to): void
    {
        if ($to === '/install' || str_contains($to, '/install')) {
            DiagnosticLogger::log('install-redirect', [
                'target' => $to,
                'source' => 'Response::redirect',
                'context' => DiagnosticLogger::requestContext(),
                'trace' => DiagnosticLogger::trace(),
            ]);
        }

        header('Location: ' . $to);
        exit;
    }

    public static function html(string $html, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    }
}

