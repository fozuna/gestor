<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ClientListViewAccessibilityTest extends TestCase
{
    public function testClientListContainsActionIconsWithAccessibilityAndFeedbackHooks(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/clients/index.php');
        self::assertIsString($view);

        self::assertStringContainsString('aria-label="Abrir perfil financeiro do cliente', $view);
        self::assertStringContainsString('aria-label="Editar cliente', $view);
        self::assertStringContainsString('aria-label="Excluir cliente', $view);
        self::assertStringContainsString('title="Abrir perfil financeiro"', $view);
        self::assertStringContainsString('title="Editar cliente"', $view);
        self::assertStringContainsString('title="Excluir cliente"', $view);
        self::assertStringContainsString('data-client-delete-open', $view);
        self::assertStringContainsString('data-loading-button', $view);
    }
}

