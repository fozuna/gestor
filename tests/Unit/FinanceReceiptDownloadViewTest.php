<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FinanceReceiptDownloadViewTest extends TestCase
{
    public function testFinanceViewContainsReceiptDownloadAction(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/finance/index.php');
        self::assertIsString($view);
        self::assertStringContainsString('/finance/payments/', $view);
        self::assertStringContainsString('/receipt?month=', $view);
        self::assertStringContainsString('Baixar recibo de pagamento', $view);
    }
}

