<?php
declare(strict_types=1);

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use RuntimeException;

final class InstallmentPlanner
{
    public function buildPlan(
        float $contractValue,
        float $entryAmount,
        ?string $firstInstallmentDate,
        array $installments
    ): array {
        if ($contractValue <= 0) {
            throw new RuntimeException('Informe um valor total válido para o projeto.');
        }

        if ($entryAmount < 0) {
            throw new RuntimeException('O valor de entrada não pode ser negativo.');
        }

        if ($entryAmount > $contractValue) {
            throw new RuntimeException('A entrada não pode ser maior que o valor total do projeto.');
        }

        $planned = [];
        $firstDate = $this->normalizeDate($firstInstallmentDate);

        if ($entryAmount > 0) {
            $planned[] = $this->makeInstallment('entry', 0, $firstDate, $entryAmount, 'Entrada');
        }

        $sequence = 1;
        foreach ($installments as $installment) {
            $amount = round((float)($installment['amount'] ?? 0), 2);
            $dueDate = $this->normalizeDate($installment['due_date'] ?? null);

            if ($amount <= 0) {
                throw new RuntimeException('Todas as parcelas mensais precisam ter valor maior que zero.');
            }

            if ($dueDate === null) {
                throw new RuntimeException('Todas as parcelas mensais precisam ter data de vencimento.');
            }

            $planned[] = $this->makeInstallment('monthly', $sequence, $dueDate, $amount, 'Parcela ' . $sequence);
            $sequence++;
        }

        $totalPlanned = array_reduce(
            $planned,
            static fn(float $carry, array $row): float => $carry + (float)$row['amount_total'],
            0.0
        );

        if (round($totalPlanned, 2) !== round($contractValue, 2)) {
            throw new RuntimeException('A soma da entrada e das parcelas deve ser igual ao valor total do projeto.');
        }

        return $planned;
    }

    public function suggestInstallments(float $remainingValue, int $count, ?string $firstInstallmentDate): array
    {
        if ($count <= 0 || $remainingValue <= 0) {
            return [];
        }

        $baseDate = $this->normalizeDate($firstInstallmentDate);
        if ($baseDate === null) {
            throw new RuntimeException('Informe a data da primeira parcela para gerar o plano automático.');
        }

        $items = [];
        $amount = round($remainingValue / $count, 2);
        $accumulated = 0.0;

        for ($i = 1; $i <= $count; $i++) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $baseDate);
            $date = $date === false ? new DateTimeImmutable($baseDate) : $date;
            $dueDate = $date->add(new DateInterval('P' . ($i - 1) . 'M'))->format('Y-m-d');
            $currentAmount = $i === $count ? round($remainingValue - $accumulated, 2) : $amount;
            $items[] = $this->makeInstallment('monthly', $i, $dueDate, $currentAmount, 'Parcela ' . $i);
            $accumulated += $currentAmount;
        }

        return $items;
    }

    private function makeInstallment(string $type, int $number, ?string $date, float $amount, string $label): array
    {
        if ($date === null) {
            throw new RuntimeException('Data da parcela inválida.');
        }

        return [
            'installment_type' => $type,
            'installment_number' => $number,
            'due_date' => $date,
            'reference_month' => (int)substr($date, 5, 2),
            'reference_year' => (int)substr($date, 0, 4),
            'amount_total' => round($amount, 2),
            'label' => $label,
        ];
    }

    private function normalizeDate(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches) === 1) {
            $year = (int)$matches[1];
            $month = (int)$matches[2];
            $day = (int)$matches[3];
            return checkdate($month, $day, $year)
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches) === 1) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];
            return checkdate($month, $day, $year)
                ? sprintf('%04d-%02d-%02d', $year, $month, $day)
                : null;
        }

        return null;
    }
}
