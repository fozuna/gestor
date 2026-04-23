<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FinanceInstallmentCalculator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FinanceInstallmentCalculatorTest extends TestCase
{
    public function testCalculatesWithoutEntry(): void
    {
        $result = (new FinanceInstallmentCalculator())->calculate(2800.0, null, 2);
        self::assertSame(2800.0, $result['total_value']);
        self::assertNull($result['entry_value']);
        self::assertSame(2800.0, $result['remaining_balance']);
        self::assertSame(2, $result['installments_count']);
        self::assertSame(1400.0, $result['installment_value']);
    }

    public function testCalculatesWithEntry(): void
    {
        $result = (new FinanceInstallmentCalculator())->calculate(2800.0, 1400.0, 2);
        self::assertSame(2800.0, $result['total_value']);
        self::assertSame(1400.0, $result['entry_value']);
        self::assertSame(1400.0, $result['remaining_balance']);
        self::assertSame(2, $result['installments_count']);
        self::assertSame(700.0, $result['installment_value']);
    }

    public function testRejectsEntryGreaterThanTotal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('O valor de entrada não pode ser maior que o valor total.');
        (new FinanceInstallmentCalculator())->calculate(100.0, 120.0, 1);
    }

    public function testRejectsInstallmentsCountWhenRemainingPositive(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Informe a quantidade de parcelas.');
        (new FinanceInstallmentCalculator())->calculate(100.0, null, 0);
    }
}

