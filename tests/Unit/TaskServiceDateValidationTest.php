<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TaskService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TaskServiceDateValidationTest extends TestCase
{
    public function testRejectsInvalidDate(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Data de prazo inválida');

        (new TaskService())->create(1, 10, 1, [
            'title' => 'Tarefa inválida',
            'status' => 'todo',
            'priority' => 'medium',
            'task_kind' => 'in_scope',
            'billable_amount' => '0',
            'due_date' => '32/13/2026',
        ]);
    }

    public function testRejectsImpossibleBrazilianDate(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Data de prazo inválida');

        (new TaskService())->create(1, 10, 1, [
            'title' => 'Tarefa com data impossível',
            'status' => 'todo',
            'priority' => 'medium',
            'task_kind' => 'in_scope',
            'billable_amount' => '0',
            'due_date' => '31/02/2026',
        ]);
    }
}
