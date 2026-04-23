<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProjectFinancialGuard;
use PHPUnit\Framework\TestCase;

final class ProjectFinancialGuardTest extends TestCase
{
    public function testNormalizeKeepsValidAmountsUnchanged(): void
    {
        $result = (new ProjectFinancialGuard())->normalize(8000.0, 2000.0, 6000.0);

        self::assertSame(8000.0, $result['contract_value']);
        self::assertSame(2000.0, $result['paid_amount']);
        self::assertSame(6000.0, $result['pending_amount']);
        self::assertFalse($result['changed']);
    }

    public function testNormalizeCapsAmountsToContract(): void
    {
        $result = (new ProjectFinancialGuard())->normalize(8000.0, 40000.0, 40000.0);

        self::assertSame(8000.0, $result['paid_amount']);
        self::assertSame(0.0, $result['pending_amount']);
        self::assertTrue($result['changed']);
        self::assertSame(40000.0, $result['raw_paid_amount']);
        self::assertSame(40000.0, $result['raw_pending_amount']);
    }
}
