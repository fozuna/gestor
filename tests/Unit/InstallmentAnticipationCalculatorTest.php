<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\InstallmentAnticipationCalculator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InstallmentAnticipationCalculatorTest extends TestCase
{
    public function testSimulateDistributesDiscountProportionally(): void
    {
        $result = (new InstallmentAnticipationCalculator())->simulate([
            ['id' => 1, 'code' => 'FIN-1', 'description' => 'Parcela 1', 'client_name' => 'Cliente', 'project_name' => 'Projeto', 'due_date' => '2099-01-10', 'amount_total' => 300.00, 'amount_paid' => 0.00],
            ['id' => 2, 'code' => 'FIN-2', 'description' => 'Parcela 2', 'client_name' => 'Cliente', 'project_name' => 'Projeto', 'due_date' => '2099-02-10', 'amount_total' => 400.00, 'amount_paid' => 0.00],
        ], 'discount', 10.0);

        self::assertSame(2, $result['selected_count']);
        self::assertSame(700.0, $result['base_amount']);
        self::assertSame(-70.0, $result['adjustment_amount']);
        self::assertSame(630.0, $result['total_amount']);
        self::assertCount(2, $result['items']);
        self::assertSame(270.0, $result['items'][0]['payment_amount']);
        self::assertSame(360.0, $result['items'][1]['payment_amount']);
    }

    public function testSimulateAppliesInterestToSelectedInstallments(): void
    {
        $result = (new InstallmentAnticipationCalculator())->simulate([
            ['id' => 1, 'code' => 'FIN-1', 'description' => 'Parcela 1', 'client_name' => 'Cliente', 'project_name' => 'Projeto', 'due_date' => '2099-01-10', 'amount_total' => 500.00, 'amount_paid' => 0.00],
        ], 'interest', 5.0);

        self::assertSame(500.0, $result['base_amount']);
        self::assertSame(25.0, $result['adjustment_amount']);
        self::assertSame(525.0, $result['total_amount']);
        self::assertSame(525.0, $result['items'][0]['payment_amount']);
    }

    public function testRejectsInvalidRate(): void
    {
        $this->expectException(RuntimeException::class);

        (new InstallmentAnticipationCalculator())->simulate([
            ['id' => 1, 'code' => 'FIN-1', 'description' => 'Parcela 1', 'client_name' => 'Cliente', 'project_name' => 'Projeto', 'due_date' => '2099-01-10', 'amount_total' => 500.00, 'amount_paid' => 0.00],
        ], 'discount', 120.0);
    }
}
