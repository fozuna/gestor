<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReceiptDownloadActionViewsTest extends TestCase
{
    public function testProjectAndClientViewsExposeReceiptDownloadAction(): void
    {
        $project = file_get_contents(dirname(__DIR__, 2) . '/resources/views/projects/show.php');
        $client = file_get_contents(dirname(__DIR__, 2) . '/resources/views/clients/profile.php');
        self::assertIsString($project);
        self::assertIsString($client);

        self::assertStringContainsString('/finance/payments/', $project);
        self::assertStringContainsString('Baixar recibo', $project);
        self::assertStringContainsString('return_to=', $project);

        self::assertStringContainsString('/finance/payments/', $client);
        self::assertStringContainsString('Baixar recibo', $client);
        self::assertStringContainsString('return_to=', $client);
    }
}

