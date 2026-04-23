<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TaskServiceClientFilterTest extends TestCase
{
    public function testMainTsImplementsRealtimeClientFilterWithStatePersistence(): void
    {
        $ts = file_get_contents(dirname(__DIR__, 2) . '/resources/ts/main.ts');
        self::assertIsString($ts);
        self::assertStringContainsString('function initTaskClientFilter()', $ts);
        self::assertStringContainsString("localStorage.setItem(storageKey, value)", $ts);
        self::assertStringContainsString('window.history.replaceState', $ts);
        self::assertStringContainsString("querySelector<HTMLSelectElement>('[data-task-client-filter]')", $ts);
        self::assertStringContainsString("querySelector<HTMLButtonElement>('[data-task-client-clear]')", $ts);
        self::assertStringContainsString('data-task-row', $ts);
        self::assertStringContainsString('data-kanban-card', $ts);
    }
}
