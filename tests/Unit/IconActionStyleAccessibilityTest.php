<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class IconActionStyleAccessibilityTest extends TestCase
{
    public function testBaseLayoutContainsGlobalIconButtonStyleGuide(): void
    {
        $base = file_get_contents(dirname(__DIR__, 2) . '/resources/views/layouts/base.php');
        self::assertIsString($base);
        self::assertStringContainsString('.icon-btn', $base);
        self::assertStringContainsString('.icon-btn--sm', $base);
        self::assertStringContainsString('.icon-btn--md', $base);
        self::assertStringContainsString('.icon-btn--lg', $base);
        self::assertStringContainsString('.icon-btn--danger', $base);
        self::assertStringContainsString('.icon-btn--primary', $base);
        self::assertStringContainsString('.icon-btn--success', $base);
        self::assertStringContainsString('.sr-only', $base);
    }

    public function testActionViewsContainTooltipAndAriaLabels(): void
    {
        $targets = [
            '/resources/views/clients/profile.php',
            '/resources/views/budgets/module.php',
            '/resources/views/budgets/proposal.php',
            '/resources/views/finance/index.php',
        ];

        foreach ($targets as $relative) {
            $content = file_get_contents(dirname(__DIR__, 2) . $relative);
            self::assertIsString($content, 'Falha ao ler: ' . $relative);
            self::assertStringContainsString('title="', $content, 'Tooltip ausente em: ' . $relative);
            self::assertStringContainsString('aria-label="', $content, 'aria-label ausente em: ' . $relative);
            self::assertStringContainsString('sr-only', $content, 'Texto para leitor de tela ausente em: ' . $relative);
        }
    }
}

