<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BudgetService;
use PHPUnit\Framework\TestCase;

final class BudgetServiceTest extends TestCase
{
    public function testCalculateFinancialSnapshotWithEntryAndInstallments(): void
    {
        $snapshot = (new BudgetService())->calculateFinancialSnapshot(10000.0, 2500.0, 5);
        self::assertSame(10000.0, $snapshot['total_value']);
        self::assertSame(2500.0, $snapshot['entry_value']);
        self::assertSame(7500.0, $snapshot['remaining_balance']);
        self::assertSame(5, $snapshot['installments_count']);
        self::assertSame(1500.0, $snapshot['installment_value']);
    }

    public function testCalculateFinancialSnapshotRejectsInvalidTotals(): void
    {
        $this->expectException(\RuntimeException::class);
        (new BudgetService())->calculateFinancialSnapshot(0.0, null, 0);
    }
}

