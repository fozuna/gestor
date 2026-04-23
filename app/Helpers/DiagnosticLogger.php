<?php
declare(strict_types=1);

namespace App\Helpers;

final class DiagnosticLogger
{
    public static function log(string $channel, array $payload): void
    {
        $baseDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            error_log('[DiagnosticLogger] Nao foi possivel criar diretorio de logs: ' . $baseDir);
            return;
        }

        $line = json_encode([
            'timestamp' => date('c'),
            'channel' => $channel,
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($line) || $line === '') {
            return;
        }

        $file = $baseDir . '/' . preg_replace('/[^a-z0-9._-]/i', '-', $channel) . '-' . date('Ymd') . '.log';
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND);
    }

    public static function trace(int $limit = 12): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
        $rows = [];

        foreach ($trace as $frame) {
            $rows[] = [
                'file' => isset($frame['file']) ? self::trimProjectRoot((string)$frame['file']) : null,
                'line' => isset($frame['line']) ? (int)$frame['line'] : null,
                'class' => isset($frame['class']) ? (string)$frame['class'] : null,
                'function' => isset($frame['function']) ? (string)$frame['function'] : null,
            ];
        }

        return $rows;
    }

    public static function requestContext(): array
    {
        return [
            'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
            'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            'query_string' => (string)($_SERVER['QUERY_STRING'] ?? ''),
            'remote_addr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'https' => (string)($_SERVER['HTTPS'] ?? ''),
            'host' => (string)($_SERVER['HTTP_HOST'] ?? ''),
            'get' => $_GET,
            'post_keys' => array_keys($_POST),
            'session' => self::safeSession(),
            'cookies' => self::safeCookies(),
        ];
    }

    private static function safeSession(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return [];
        }

        $snapshot = [];
        foreach ($_SESSION as $key => $value) {
            $snapshot[(string)$key] = self::maskValue($value);
        }

        return $snapshot;
    }

    private static function safeCookies(): array
    {
        $snapshot = [];
        foreach ($_COOKIE as $key => $value) {
            $snapshot[(string)$key] = is_string($value)
                ? ['length' => strlen($value), 'preview' => substr($value, 0, 12)]
                : self::maskValue($value);
        }

        return $snapshot;
    }

    private static function maskValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $masked = [];
            foreach ($value as $k => $v) {
                $masked[(string)$k] = self::maskValue($v);
            }
            return $masked;
        }

        if (is_object($value)) {
            return ['type' => get_class($value)];
        }

        if (is_string($value)) {
            return [
                'type' => 'string',
                'length' => strlen($value),
                'preview' => substr($value, 0, 24),
            ];
        }

        return $value;
    }

    private static function trimProjectRoot(string $path): string
    {
        $root = str_replace('\\', '/', dirname(__DIR__, 2));
        $normalized = str_replace('\\', '/', $path);
        return str_starts_with($normalized, $root)
            ? ltrim(substr($normalized, strlen($root)), '/')
            : $normalized;
    }
}
