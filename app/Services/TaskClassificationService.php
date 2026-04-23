<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class TaskClassificationService
{
    public function normalize(string $taskKind, float $billableAmount): array
    {
        $allowedKinds = ['out_of_scope', 'one_off', 'in_scope'];
        if (!in_array($taskKind, $allowedKinds, true)) {
            throw new RuntimeException('Classificação de tarefa inválida.');
        }

        if ($taskKind === 'in_scope') {
            return [
                'task_kind' => $taskKind,
                'billing_type' => 'informative',
                'billable_amount' => 0.0,
            ];
        }

        if ($billableAmount <= 0) {
            throw new RuntimeException('Tarefas cobráveis precisam ter valor maior que zero.');
        }

        return [
            'task_kind' => $taskKind,
            'billing_type' => 'billable',
            'billable_amount' => round($billableAmount, 2),
        ];
    }
}
