<?php
declare(strict_types=1);

namespace App\Services;

final class ProjectFinancialGuard
{
    public function normalize(float $contractValue, float $paidAmount, float $pendingAmount): array
    {
        $contract = max(0.0, round($contractValue, 2));
        $paidRaw = max(0.0, round($paidAmount, 2));
        $pendingRaw = max(0.0, round($pendingAmount, 2));

        $paid = min($paidRaw, $contract);
        $pending = min($pendingRaw, max(0.0, $contract - $paid));

        return [
            'contract_value' => $contract,
            'paid_amount' => round($paid, 2),
            'pending_amount' => round($pending, 2),
            'changed' => round($paidRaw, 2) !== round($paid, 2) || round($pendingRaw, 2) !== round($pending, 2),
            'raw_paid_amount' => $paidRaw,
            'raw_pending_amount' => $pendingRaw,
        ];
    }
}
