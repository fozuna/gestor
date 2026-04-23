<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class PaymentValidationService
{
    public function validate(array $installment, float $amountPaid, string $paymentDate, ?array $existingPayment): void
    {
        if ($installment === []) {
            throw new RuntimeException('Parcela não encontrada.');
        }

        $expected = round((float)($installment['amount_total'] ?? 0), 2);
        $currentPaid = round((float)($installment['amount_paid'] ?? 0), 2);
        $remaining = round($expected - $currentPaid, 2);

        if (($installment['status'] ?? 'pending') === 'paid' || $remaining <= 0) {
            throw new RuntimeException('Esta parcela já foi baixada e não pode receber novo pagamento.');
        }

        if ($paymentDate === '') {
            throw new RuntimeException('Informe uma data de pagamento válida.');
        }

        if ($amountPaid <= 0) {
            throw new RuntimeException('Informe um valor pago maior que zero.');
        }

        if (round($amountPaid, 2) > $remaining) {
            throw new RuntimeException('O valor pago não pode ser maior que o saldo restante da parcela.');
        }

        if ($existingPayment !== null) {
            $lastAmount = round((float)($existingPayment['amount'] ?? 0), 2);
            $lastDate = substr((string)($existingPayment['paid_at'] ?? ''), 0, 10);
            if ($lastAmount === round($amountPaid, 2) && $lastDate === $paymentDate) {
                throw new RuntimeException('Já existe um pagamento idêntico registrado para esta parcela.');
            }
        }
    }

    public function validateAnticipation(
        array $installments,
        string $paymentDate,
        string $paymentMethod,
        string $adjustmentMode,
        float $adjustmentRate
    ): void {
        if ($installments === []) {
            throw new RuntimeException('Selecione ao menos uma parcela para antecipar.');
        }

        if ($paymentDate === '') {
            throw new RuntimeException('Informe a data efetiva da antecipação.');
        }

        if ($paymentDate > date('Y-m-d')) {
            throw new RuntimeException('A data efetiva da antecipação não pode ser futura.');
        }

        if (!in_array($paymentMethod, ['pix', 'boleto', 'transferencia', 'cartao', 'dinheiro', 'outro'], true)) {
            throw new RuntimeException('Método de pagamento inválido para a antecipação.');
        }

        if (!in_array($adjustmentMode, ['none', 'discount', 'interest'], true)) {
            throw new RuntimeException('Modo de ajuste da antecipação inválido.');
        }

        if ($adjustmentRate < 0 || $adjustmentRate > 100) {
            throw new RuntimeException('A taxa da antecipação deve estar entre 0% e 100%.');
        }

        $seen = [];
        foreach ($installments as $installment) {
            $invoiceId = (int)($installment['id'] ?? 0);
            if ($invoiceId <= 0 || isset($seen[$invoiceId])) {
                throw new RuntimeException('A seleção de parcelas da antecipação é inválida.');
            }
            $seen[$invoiceId] = true;

            $status = (string)($installment['status'] ?? 'pending');
            $remaining = round((float)($installment['amount_total'] ?? 0) - (float)($installment['amount_paid'] ?? 0), 2);
            $dueDate = (string)($installment['due_date'] ?? '');

            if ($status === 'paid' || $remaining <= 0) {
                throw new RuntimeException('A antecipação só pode incluir parcelas ainda não quitadas.');
            }
            if ($dueDate === '' || $dueDate <= $paymentDate) {
                throw new RuntimeException('A antecipação só pode ser aplicada a parcelas com vencimento posterior à data efetiva do pagamento.');
            }
        }
    }
}
