<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class InstallmentAnticipationCalculator
{
    public function simulate(array $installments, string $adjustmentMode, float $adjustmentRate): array
    {
        $adjustmentMode = trim($adjustmentMode);
        if (!in_array($adjustmentMode, ['none', 'discount', 'interest'], true)) {
            throw new RuntimeException('Modo de ajuste da antecipação inválido.');
        }
        if ($adjustmentRate < 0 || $adjustmentRate > 100) {
            throw new RuntimeException('A taxa da antecipação deve estar entre 0% e 100%.');
        }

        $eligible = [];
        $baseAmount = 0.0;
        foreach ($installments as $installment) {
            $remainingAmount = round((float)($installment['amount_total'] ?? 0) - (float)($installment['amount_paid'] ?? 0), 2);
            if ($remainingAmount <= 0) {
                continue;
            }

            $eligible[] = [
                'invoice_id' => (int)($installment['id'] ?? 0),
                'code' => (string)($installment['code'] ?? ''),
                'description' => (string)($installment['description'] ?? ''),
                'client_name' => (string)($installment['client_name'] ?? ''),
                'project_name' => (string)($installment['project_name'] ?? ''),
                'due_date' => (string)($installment['due_date'] ?? ''),
                'original_amount_total' => round((float)($installment['amount_total'] ?? 0), 2),
                'original_amount_paid' => round((float)($installment['amount_paid'] ?? 0), 2),
                'settled_amount' => $remainingAmount,
            ];
            $baseAmount = round($baseAmount + $remainingAmount, 2);
        }

        if ($eligible === []) {
            throw new RuntimeException('Nenhuma parcela elegível foi selecionada para antecipação.');
        }

        $unsignedAdjustment = $adjustmentMode === 'none'
            ? 0.0
            : round($baseAmount * ($adjustmentRate / 100), 2);
        $adjustmentAmount = match ($adjustmentMode) {
            'discount' => round($unsignedAdjustment * -1, 2),
            'interest' => $unsignedAdjustment,
            default => 0.0,
        };

        $totalAmount = round($baseAmount + $adjustmentAmount, 2);
        if ($totalAmount <= 0) {
            throw new RuntimeException('O total da antecipação deve ser maior que zero.');
        }

        $allocated = 0.0;
        $items = [];
        $itemsCount = count($eligible);
        foreach ($eligible as $index => $installment) {
            $portion = $baseAmount > 0 ? round(($installment['settled_amount'] / $baseAmount) * $adjustmentAmount, 2) : 0.0;
            if ($index === $itemsCount - 1) {
                $portion = round($adjustmentAmount - $allocated, 2);
            }
            $allocated = round($allocated + $portion, 2);

            $paymentAmount = round($installment['settled_amount'] + $portion, 2);
            if ($paymentAmount <= 0) {
                throw new RuntimeException('A distribuição do ajuste deixou uma parcela com valor inválido.');
            }

            $items[] = $installment + [
                'adjustment_amount' => $portion,
                'payment_amount' => $paymentAmount,
            ];
        }

        return [
            'selected_count' => $itemsCount,
            'base_amount' => $baseAmount,
            'adjustment_mode' => $adjustmentMode,
            'adjustment_rate' => round($adjustmentRate, 4),
            'adjustment_amount' => $adjustmentAmount,
            'total_amount' => $totalAmount,
            'items' => $items,
        ];
    }
}
