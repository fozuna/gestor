<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FinanceAnticipationViewTest extends TestCase
{
    public function testFinanceViewContainsAnticipationActions(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/finance/index.php');
        self::assertIsString($view);
        self::assertStringContainsString('Antecipação de parcelas', $view);
        self::assertStringContainsString('/finance/installments/anticipation/simulate', $view);
        self::assertStringContainsString('/finance/installments/anticipation/process', $view);
        self::assertStringContainsString('Histórico recente de antecipações', $view);
    }
}
