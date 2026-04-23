<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\InstallmentPlanner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InstallmentPlannerTest extends TestCase
{
    public function testBuildPlanWithEntryAndMonthlyInstallments(): void
    {
        $planner = new InstallmentPlanner();
        $plan = $planner->buildPlan(1500, 300, '2026-05-10', [
            ['due_date' => '2026-05-10', 'amount' => 400],
            ['due_date' => '2026-06-10', 'amount' => 400],
            ['due_date' => '2026-07-10', 'amount' => 400],
        ]);

        self::assertCount(4, $plan);
        self::assertSame('entry', $plan[0]['installment_type']);
        self::assertSame('monthly', $plan[1]['installment_type']);
        self::assertSame(7, $plan[3]['reference_month']);
    }

    public function testSuggestInstallmentsGeneratesExpectedCount(): void
    {
        $planner = new InstallmentPlanner();
        $plan = $planner->suggestInstallments(900, 3, '2026-05-10');

        self::assertCount(3, $plan);
        self::assertSame(300.0, $plan[0]['amount_total']);
        self::assertSame('2026-07-10', $plan[2]['due_date']);
    }

    public function testRejectsInvalidInstallmentDate(): void
    {
        $this->expectException(RuntimeException::class);

        (new InstallmentPlanner())->buildPlan(1000, 0, null, [
            ['due_date' => '31/02/2026', 'amount' => 1000],
        ]);
    }
}
