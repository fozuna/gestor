<?php
declare(strict_types=1);

namespace App\Helpers;

use DateTimeImmutable;
use DateTimeZone;

final class Security
{
    public static function sanitizeString(?string $v): string
    {
        $v = $v ?? '';
        $v = trim($v);
        $v = filter_var($v, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
        return is_string($v) ? $v : '';
    }

    public static function sanitizeEmail(?string $v): string
    {
        $v = self::sanitizeString($v);
        $v = filter_var($v, FILTER_SANITIZE_EMAIL);
        return is_string($v) ? $v : '';
    }

    public static function isValidEmail(?string $value): bool
    {
        $email = self::sanitizeEmail($value);
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function parseMoney(?string $value): float
    {
        $value = self::sanitizeString($value);
        if ($value === '') {
            return 0.0;
        }

        $normalized = str_replace(['R$', ' '], '', $value);
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? round((float)$normalized, 2) : 0.0;
    }

    public static function parseDate(?string $value): ?string
    {
        $value = self::sanitizeString($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) === 1) {
            $year = (int)$matches[1];
            $month = (int)$matches[2];
            $day = (int)$matches[3];
            return checkdate($month, $day, $year)
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches) === 1) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];
            return checkdate($month, $day, $year)
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : null;
        }

        return null;
    }

    public static function formatDate(?string $value): string
    {
        $parsed = self::parseDate($value);
        if ($parsed === null) {
            return '';
        }

        return sprintf('%s/%s/%s', substr($parsed, 8, 2), substr($parsed, 5, 2), substr($parsed, 0, 4));
    }

    public static function appTimezone(): string
    {
        $tz = (string)($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'America/Campo_Grande');
        if (!in_array($tz, timezone_identifiers_list(), true)) {
            return 'America/Campo_Grande';
        }
        return $tz;
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(self::appTimezone()));
    }

    public static function formatDatabaseDate(?string $value): string
    {
        $value = self::sanitizeString($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return self::formatDate($value);
        }

        $dt = self::parseDatabaseDateTimeToAppTimezone($value);
        return $dt?->format('d/m/Y') ?? '';
    }

    public static function formatDatabaseDateTime(?string $value): string
    {
        $dt = self::parseDatabaseDateTimeToAppTimezone($value);
        return $dt?->format('d/m/Y H:i:s') ?? '';
    }

    public static function parseDatabaseDateTimeToAppTimezone(?string $value): ?DateTimeImmutable
    {
        $value = self::sanitizeString($value);
        if ($value === '') {
            return null;
        }

        $sourceUtc = new DateTimeZone('UTC');
        $target = new DateTimeZone(self::appTimezone());
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:sP',
            'Y-m-d H:i:s.u',
        ];

        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $value, $sourceUtc);
            if ($dt instanceof DateTimeImmutable) {
                return $dt->setTimezone($target);
            }
        }

        try {
            $dt = new DateTimeImmutable($value, $sourceUtc);
            return $dt->setTimezone($target);
        } catch (\Throwable) {
            return null;
        }
    }
}
