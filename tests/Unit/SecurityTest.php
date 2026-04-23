<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Security;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    public function testParseDateAcceptsValidBrazilianFormat(): void
    {
        self::assertSame('2026-05-10', Security::parseDate('10/05/2026'));
    }

    public function testParseDateRejectsInvalidCalendarDate(): void
    {
        self::assertNull(Security::parseDate('31/02/2026'));
        self::assertNull(Security::parseDate('2026-02-31'));
    }

    public function testFormatDateReturnsBrazilianPattern(): void
    {
        self::assertSame('10/05/2026', Security::formatDate('2026-05-10'));
    }
}
