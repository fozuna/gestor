<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class FinanceInstallmentCalculator
{
    public function calculate(float $totalValue, ?float $entryValue, int $installmentsCount): array
    {
        $total = round($totalValue, 2);
        if ($total <= 0) {
            throw new RuntimeException('Informe um valor total válido.');
        }

        $entry = $entryValue === null ? null : round($entryValue, 2);
        if ($entry !== null && $entry < 0) {
            throw new RuntimeException('O valor de entrada não pode ser negativo.');
        }
        if ($entry !== null && $entry > $total) {
            throw new RuntimeException('O valor de entrada não pode ser maior que o valor total.');
        }

        $remaining = round($total - ($entry ?? 0.0), 2);
        if ($remaining > 0 && $installmentsCount <= 0) {
            throw new RuntimeException('Informe a quantidade de parcelas.');
        }

        $installmentValue = $remaining > 0 ? round($remaining / max(1, $installmentsCount), 2) : 0.0;

        return [
            'total_value' => $total,
            'entry_value' => $entry,
            'remaining_balance' => $remaining,
            'installments_count' => max(0, $installmentsCount),
            'installment_value' => $installmentValue,
        ];
    }
}

