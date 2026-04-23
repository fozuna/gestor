<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TasksViewClientFilterTest extends TestCase
{
    public function testTasksViewContainsClientFilterControls(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/tasks/index.php');
        self::assertIsString($view);

        self::assertStringContainsString('name="client"', $view);
        self::assertStringContainsString('data-task-client-filter', $view);
        self::assertStringContainsString('data-task-client-clear', $view);
        self::assertStringContainsString('data-task-row', $view);
        self::assertStringContainsString('data-task-client-id', $view);
    }
}

