<?php
declare(strict_types=1);

namespace App\Helpers;

final class RuntimeConfigLoader
{
    /**
     * @return array{environment:string,source:string,config:array<string,mixed>,override_used:bool}
     */
    public static function load(string $configDir, ?string $appEnv = null, ?string $host = null): array
    {
        $environment = self::detectEnvironment($appEnv, $host);
        $basePath = rtrim($configDir, '\\/');
        $primaryPath = $basePath . DIRECTORY_SEPARATOR . 'config.php.' . $environment;
        $overridePath = $basePath . DIRECTORY_SEPARATOR . 'config.php';

        $config = self::readConfig($primaryPath);
        $overrideUsed = false;

        if (is_file($overridePath)) {
            $override = self::readConfig($overridePath);
            $config = array_replace_recursive($config, $override);
            $overrideUsed = true;
        }

        return [
            'environment' => $environment,
            'source' => basename($primaryPath),
            'config' => $config,
            'override_used' => $overrideUsed,
        ];
    }

    public static function detectEnvironment(?string $appEnv = null, ?string $host = null): string
    {
        $normalizedEnv = strtolower(trim((string)$appEnv));
        if (in_array($normalizedEnv, ['local', 'dev', 'development', 'test', 'testing'], true)) {
            return 'local';
        }

        if (in_array($normalizedEnv, ['production', 'prod', 'live'], true)) {
            return 'production';
        }

        $normalizedHost = strtolower(trim((string)$host));
        $normalizedHost = preg_replace('/:\d+$/', '', $normalizedHost) ?: '';

        if ($normalizedHost === '' || $normalizedHost === 'localhost' || $normalizedHost === '127.0.0.1' || $normalizedHost === '::1') {
            return 'local';
        }

        foreach (['.local', '.localhost', '.test'] as $suffix) {
            if (str_ends_with($normalizedHost, $suffix)) {
                return 'local';
            }
        }

        return 'production';
    }

    /**
     * @return array<string,mixed>
     */
    private static function readConfig(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $loaded = require $path;
        return is_array($loaded) ? $loaded : [];
    }
}
