<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DashboardViewModernizationTest extends TestCase
{
    public function testDashboardViewContainsFinancialAndOperationalSections(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/dashboard/index.php');
        self::assertIsString($view);
        self::assertStringContainsString('Fluxo Financeiro Anual', $view);
        self::assertStringContainsString('Indicadores Financeiros', $view);
        self::assertStringContainsString('name="year"', $view);
        self::assertStringContainsString('name="project_status"', $view);
        self::assertStringContainsString('name="client_id"', $view);
        self::assertStringContainsString('Projetos', $view);
        self::assertStringContainsString('Tarefas', $view);
    }
}

