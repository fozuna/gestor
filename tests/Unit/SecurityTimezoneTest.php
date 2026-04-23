<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Security;
use PHPUnit\Framework\TestCase;

final class SecurityTimezoneTest extends TestCase
{
    public function testDatabaseDateTimeIsConvertedFromUtcToCampoGrande(): void
    {
        $converted = Security::formatDatabaseDateTime('2026-04-10 03:30:00');
        self::assertSame('09/04/2026 23:30:00', $converted);
    }

    public function testDatabaseDateWithoutTimePreservesCalendarDate(): void
    {
        $converted = Security::formatDatabaseDate('2026-04-10');
        self::assertSame('10/04/2026', $converted);
    }
}

