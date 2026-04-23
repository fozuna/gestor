<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PaymentValidationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PaymentValidationServiceTest extends TestCase
{
    public function testRejectsDuplicatePaymentWithSameDateAndAmount(): void
    {
        $this->expectException(RuntimeException::class);

        (new PaymentValidationService())->validate(
            ['status' => 'pending', 'amount_total' => 500, 'amount_paid' => 100],
            100,
            '2026-05-10',
            ['id' => 10, 'amount' => 100, 'paid_at' => '2026-05-10 12:00:00']
        );
    }

    public function testRejectsOverpaymentAgainstRemainingBalance(): void
    {
        $this->expectException(RuntimeException::class);

        (new PaymentValidationService())->validate(
            ['status' => 'pending', 'amount_total' => 500, 'amount_paid' => 450],
            60,
            '2026-05-11',
            ['id' => 10, 'amount' => 100, 'paid_at' => '2026-05-10 12:00:00']
        );
    }

    public function testAcceptsAdditionalPaymentWhileBalanceExists(): void
    {
        $service = new PaymentValidationService();
        $service->validate(
            ['status' => 'pending', 'amount_total' => 500, 'amount_paid' => 150],
            200,
            '2026-05-11',
            ['id' => 10, 'amount' => 150, 'paid_at' => '2026-05-10 12:00:00']
        );

        self::assertTrue(true);
    }

    public function testRejectsAnticipationForInstallmentWithoutFutureDueDate(): void
    {
        $this->expectException(RuntimeException::class);

        (new PaymentValidationService())->validateAnticipation([
            ['id' => 1, 'status' => 'pending', 'amount_total' => 500, 'amount_paid' => 0, 'due_date' => '2026-04-10'],
        ], '2026-04-10', 'pix', 'discount', 5.0);
    }

    public function testAcceptsFutureInstallmentAnticipation(): void
    {
        (new PaymentValidationService())->validateAnticipation([
            ['id' => 1, 'status' => 'pending', 'amount_total' => 500, 'amount_paid' => 100, 'due_date' => '2099-04-10'],
            ['id' => 2, 'status' => 'pending', 'amount_total' => 300, 'amount_paid' => 0, 'due_date' => '2099-05-10'],
        ], date('Y-m-d'), 'pix', 'discount', 5.0);

        self::assertTrue(true);
    }
}
