<?php
declare(strict_types=1);

namespace App\Services;

final class BrandLogoService
{
    private static array $webCache = [];
    private static array $dataUriCache = [];

    public function __construct(
        private readonly ?string $publicDir = null
    ) {
    }

    public function pickVariantByBackground(string $background): string
    {
        $rgb = $this->parseBackgroundColor($background);
        if ($rgb === null) {
            return 'escura';
        }

        $luminance = (($rgb[0] * 299) + ($rgb[1] * 587) + ($rgb[2] * 114)) / 1000;
        return $luminance < 140 ? 'clara' : 'escura';
    }

    public function webLogoForBackground(string $background): array
    {
        $variant = $this->pickVariantByBackground($background);
        $path = $this->logoPathByVariant($variant);
        $cacheKey = $variant . '|' . $path;

        if (isset(self::$webCache[$cacheKey])) {
            return self::$webCache[$cacheKey];
        }

        if (is_file($path)) {
            $mtime = @filemtime($path) ?: time();
            $result = [
                'variant' => $variant,
                'src' => '/assets/images/logo-' . $variant . '.png?v=' . $mtime,
            ];
            self::$webCache[$cacheKey] = $result;
            return $result;
        }

        $fallback = $this->fallbackSvgDataUri('TRAXTER', $variant);
        error_log('[BrandLogoService] Arquivo de logo não encontrado: ' . $path);
        $result = [
            'variant' => $variant,
            'src' => $fallback,
        ];
        self::$webCache[$cacheKey] = $result;
        return $result;
    }

    public function pdfLogoDataUriForBackground(string $tenantName, string $background, ?string $customLogoPath = null): string
    {
        if ($customLogoPath !== null && $customLogoPath !== '' && is_file($customLogoPath)) {
            $fromCustom = $this->fileToDataUri($customLogoPath);
            if ($fromCustom !== null) {
                return $fromCustom;
            }
        }

        $variant = $this->pickVariantByBackground($background);
        $path = $this->logoPathByVariant($variant);
        $fromVariant = $this->fileToDataUri($path);
        if ($fromVariant !== null) {
            return $fromVariant;
        }

        error_log('[BrandLogoService] Fallback de logo para PDF por arquivo ausente: ' . $path);
        return $this->fallbackSvgDataUri($tenantName, $variant);
    }

    private function logoPathByVariant(string $variant): string
    {
        return $this->resolvePublicDir() . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo-' . $variant . '.png';
    }

    private function resolvePublicDir(): string
    {
        if ($this->publicDir !== null && $this->publicDir !== '') {
            return rtrim($this->publicDir, '\\/');
        }
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
    }

    private function fileToDataUri(string $filePath): ?string
    {
        if (!is_file($filePath)) {
            return null;
        }

        $mtime = @filemtime($filePath) ?: 0;
        $cacheKey = $filePath . '|' . $mtime;
        if (isset(self::$dataUriCache[$cacheKey])) {
            return self::$dataUriCache[$cacheKey];
        }

        $content = @file_get_contents($filePath);
        if (!is_string($content) || $content === '') {
            return null;
        }

        $ext = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($content);
        self::$dataUriCache[$cacheKey] = $dataUri;
        return $dataUri;
    }

    private function fallbackSvgDataUri(string $tenantName, string $variant): string
    {
        $letters = strtoupper(substr((string)preg_replace('/[^A-Za-z]/', '', $tenantName), 0, 2));
        if ($letters === '') {
            $letters = 'TX';
        }

        $bg = $variant === 'clara' ? '#111827' : '#FE5516';
        $fg = '#FFFFFF';
        $badgeFg = $variant === 'clara' ? '#111827' : '#FE5516';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="52" viewBox="0 0 220 52">'
            . '<rect width="220" height="52" rx="10" fill="' . $bg . '"/>'
            . '<circle cx="26" cy="26" r="16" fill="#ffffff" />'
            . '<text x="26" y="31" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" font-weight="700" fill="' . $badgeFg . '">'
            . $this->esc($letters)
            . '</text>'
            . '<text x="50" y="32" font-family="Arial, sans-serif" font-size="16" font-weight="700" fill="' . $fg . '">TRAXTER</text>'
            . '</svg>';

        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    private function parseBackgroundColor(string $background): ?array
    {
        $value = strtolower(trim($background));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^#([0-9a-f]{3})$/i', $value, $m) === 1) {
            $h = $m[1];
            return [
                hexdec($h[0] . $h[0]),
                hexdec($h[1] . $h[1]),
                hexdec($h[2] . $h[2]),
            ];
        }

        if (preg_match('/^#([0-9a-f]{6})$/i', $value, $m) === 1) {
            $h = $m[1];
            return [
                hexdec(substr($h, 0, 2)),
                hexdec(substr($h, 2, 2)),
                hexdec(substr($h, 4, 2)),
            ];
        }

        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/', $value, $m) === 1) {
            return [
                min(255, (int)$m[1]),
                min(255, (int)$m[2]),
                min(255, (int)$m[3]),
            ];
        }

        return null;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

