<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\DB;
use App\Helpers\Security;
use App\Repositories\FinanceRepository;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;

final class FinanceService
{
    public function __construct(
        private readonly FinanceRepository $finance = new FinanceRepository(),
        private readonly PaymentValidationService $payments = new PaymentValidationService(),
        private readonly PaymentReceiptService $receipts = new PaymentReceiptService(),
        private readonly AuditLogService $audit = new AuditLogService(),
        private readonly BackupService $backup = new BackupService()
    ) {
    }

    public function summary(int $tenantId, ?int $month = null, ?int $year = null): array
    {
        return $this->finance->summary($tenantId, $month, $year);
    }

    public function listByTenant(int $tenantId, ?int $month = null, ?int $year = null): array
    {
        return $this->finance->listByTenant($tenantId, $month, $year);
    }

    public function anticipationContext(int $tenantId, ?int $clientId = null, ?int $projectId = null): array
    {
        return [
            'clients' => $this->finance->clientsWithFutureInstallments($tenantId),
            'projects' => $this->finance->projectsWithFutureInstallments($tenantId, $clientId),
            'candidates' => $this->finance->listAnticipationCandidates($tenantId, $clientId, $projectId),
            'history' => $this->finance->listRecentAnticipations($tenantId),
            'filters' => [
                'client_id' => $clientId,
                'project_id' => $projectId,
            ],
        ];
    }

    public function simulateInstallmentAnticipation(int $tenantId, ?int $clientId, ?int $projectId, array $invoiceIds, array $data): array
    {
        $paymentDate = (string)($data['payment_date'] ?? '');
        $paymentMethod = $this->normalizePaymentMethod((string)($data['payment_method'] ?? ''));
        $adjustmentMode = $this->normalizeAnticipationAdjustmentMode((string)($data['adjustment_mode'] ?? 'none'));
        $adjustmentRate = round((float)($data['adjustment_rate'] ?? 0), 4);
        $note = trim((string)($data['note'] ?? ''));

        $installments = $this->finance->findAnticipationInvoicesByIds($tenantId, $invoiceIds, $clientId, $projectId);
        if (count($installments) !== count(array_values(array_unique(array_map(static fn(mixed $value): int => (int)$value, $invoiceIds))))) {
            throw new RuntimeException('Uma ou mais parcelas selecionadas não estão elegíveis para antecipação.');
        }

        $this->assertAnticipationSelectionBelongsToSingleClient($installments);
        $this->payments->validateAnticipation($installments, $paymentDate, $paymentMethod, $adjustmentMode, $adjustmentRate);

        $simulation = (new InstallmentAnticipationCalculator())->simulate($installments, $adjustmentMode, $adjustmentRate);
        $simulation['client_id'] = $clientId ?: (int)($installments[0]['client_id'] ?? 0);
        $simulation['project_id'] = $projectId ?: null;
        $simulation['payment_date'] = $paymentDate;
        $simulation['payment_method'] = $paymentMethod;
        $simulation['note'] = $note;

        return $simulation;
    }

    public function processInstallmentAnticipation(
        int $tenantId,
        ?int $clientId,
        ?int $projectId,
        ?int $userId,
        array $invoiceIds,
        array $data
    ): array {
        $simulation = $this->simulateInstallmentAnticipation($tenantId, $clientId, $projectId, $invoiceIds, $data);
        $paymentDate = (string)$simulation['payment_date'];
        $paymentMethod = (string)$simulation['payment_method'];
        $note = (string)$simulation['note'];
        $items = is_array($simulation['items'] ?? null) ? $simulation['items'] : [];

        $pdo = DB::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        $generatedReceiptAbsolutePaths = [];

        try {
            $anticipationId = $this->finance->createInstallmentAnticipation([
                'tenant_id' => $tenantId,
                'client_id' => (int)$simulation['client_id'],
                'project_id' => $simulation['project_id'] !== null ? (int)$simulation['project_id'] : null,
                'adjustment_mode' => (string)$simulation['adjustment_mode'],
                'adjustment_rate' => (float)$simulation['adjustment_rate'],
                'base_amount' => (float)$simulation['base_amount'],
                'adjustment_amount' => (float)$simulation['adjustment_amount'],
                'total_amount' => (float)$simulation['total_amount'],
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'note' => $note,
                'processed_by_user_id' => $userId,
            ]);

            $paymentIds = [];
            $receiptNumbers = [];

            foreach ($items as $item) {
                $invoiceId = (int)($item['invoice_id'] ?? 0);
                $settledAmount = round((float)($item['settled_amount'] ?? 0), 2);
                $paymentAmount = round((float)($item['payment_amount'] ?? 0), 2);
                $adjustmentAmount = round((float)($item['adjustment_amount'] ?? 0), 2);
                $paymentNote = $note !== ''
                    ? 'Antecipacao #' . $anticipationId . ' - ' . $note
                    : 'Antecipacao #' . $anticipationId;

                $paymentId = $this->finance->registerPayment([
                    'tenant_id' => $tenantId,
                    'invoice_id' => $invoiceId,
                    'amount' => $paymentAmount,
                    'paid_at' => $paymentDate . ' 12:00:00',
                    'payment_method' => $paymentMethod,
                    'note' => $paymentNote,
                    'received_by_user_id' => $userId,
                ]);

                $this->finance->settleInvoiceAsAnticipated($tenantId, $invoiceId, $paymentDate . ' 12:00:00');
                $this->finance->updateCashFlowStatus($tenantId, $invoiceId, 'settled');

                $receipt = $this->receipts->generateForPayment($tenantId, $paymentId);
                $generatedReceiptAbsolutePaths[] = (string)($receipt['absolute_path'] ?? '');
                $this->finance->attachPaymentReceipt(
                    $tenantId,
                    $paymentId,
                    (string)$receipt['number'],
                    (string)$receipt['relative_path'],
                    (int)$receipt['bytes']
                );

                $this->finance->createInstallmentAnticipationItem([
                    'anticipation_id' => $anticipationId,
                    'invoice_id' => $invoiceId,
                    'payment_id' => $paymentId,
                    'original_due_date' => (string)($item['due_date'] ?? ''),
                    'original_amount_total' => round((float)($item['original_amount_total'] ?? 0), 2),
                    'original_amount_paid' => round((float)($item['original_amount_paid'] ?? 0), 2),
                    'settled_amount' => $settledAmount,
                    'payment_amount' => $paymentAmount,
                    'adjustment_amount' => $adjustmentAmount,
                    'receipt_number' => (string)$receipt['number'],
                ]);

                $paymentIds[] = $paymentId;
                $receiptNumbers[] = (string)$receipt['number'];
            }

            $this->audit->record($tenantId, $userId, 'installment_anticipation', $anticipationId, 'finance.installment.anticipated', [
                'client_id' => $simulation['client_id'],
                'project_id' => $simulation['project_id'],
                'invoice_ids' => array_column($items, 'invoice_id'),
                'payment_ids' => $paymentIds,
                'receipt_numbers' => $receiptNumbers,
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'base_amount' => $simulation['base_amount'],
                'adjustment_mode' => $simulation['adjustment_mode'],
                'adjustment_rate' => $simulation['adjustment_rate'],
                'adjustment_amount' => $simulation['adjustment_amount'],
                'total_amount' => $simulation['total_amount'],
            ]);

            $this->backup->storeFinancialSnapshot($tenantId, 'installment-anticipation', [
                'anticipation_id' => $anticipationId,
                'simulation' => $simulation,
                'payment_ids' => $paymentIds,
                'receipt_numbers' => $receiptNumbers,
            ]);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return [
                'anticipation_id' => $anticipationId,
                'selected_count' => (int)$simulation['selected_count'],
                'total_amount' => (float)$simulation['total_amount'],
                'payment_ids' => $paymentIds,
                'receipt_numbers' => $receiptNumbers,
            ];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($generatedReceiptAbsolutePaths as $path) {
                if (is_string($path) && $path !== '' && is_file($path)) {
                    @unlink($path);
                }
            }
            throw $e;
        }
    }

    public function settleInstallment(int $tenantId, int $invoiceId, ?int $userId, array $data): void
    {
        $invoice = $this->finance->findInvoiceById($tenantId, $invoiceId);
        if ($invoice !== null && isset($invoice['computed_status'])) {
            $invoice['status'] = $invoice['computed_status'];
        }
        $existingPayment = $this->finance->findPaymentByInvoice($tenantId, $invoiceId);
        $amountPaid = round((float)($data['amount_paid'] ?? 0), 2);
        $paymentDate = (string)($data['payment_date'] ?? '');
        $paymentMethod = $this->normalizePaymentMethod((string)($data['payment_method'] ?? ''));

        if ($paymentDate === '') {
            throw new RuntimeException('Informe a data do pagamento.');
        }

        if ($paymentDate > date('Y-m-d')) {
            throw new RuntimeException('A data do pagamento não pode ser futura.');
        }

        $this->payments->validate($invoice ?? [], $amountPaid, $paymentDate, $existingPayment);

        $pdo = DB::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        $generatedReceiptAbsolutePath = null;

        try {
            $currentPaid = round((float)($invoice['amount_paid'] ?? 0), 2);
            $expected = round((float)($invoice['amount_total'] ?? 0), 2);
            $newPaidTotal = round($currentPaid + $amountPaid, 2);
            $status = $newPaidTotal >= $expected ? 'paid' : 'pending';
            $cashFlowStatus = $status === 'paid'
                ? 'settled'
                : (((string)($invoice['due_date'] ?? '') < date('Y-m-d')) ? 'overdue' : 'pending');

            $paymentId = $this->finance->registerPayment([
                'tenant_id' => $tenantId,
                'invoice_id' => $invoiceId,
                'amount' => $amountPaid,
                'paid_at' => $paymentDate . ' 12:00:00',
                'payment_method' => $paymentMethod,
                'note' => (string)($data['note'] ?? ''),
                'received_by_user_id' => $userId,
            ]);

            $this->finance->settleInvoice($tenantId, $invoiceId, $newPaidTotal, $paymentDate . ' 12:00:00', $status);
            $this->finance->updateCashFlowStatus($tenantId, $invoiceId, $cashFlowStatus);

            $receipt = $this->receipts->generateForPayment($tenantId, $paymentId);
            $generatedReceiptAbsolutePath = (string)($receipt['absolute_path'] ?? null);
            $this->finance->attachPaymentReceipt(
                $tenantId,
                $paymentId,
                (string)$receipt['number'],
                (string)$receipt['relative_path'],
                (int)$receipt['bytes']
            );

            $this->audit->record($tenantId, $userId, 'invoice', $invoiceId, 'finance.installment.settled', [
                'payment_id' => $paymentId,
                'receipt_number' => $receipt['number'] ?? null,
                'receipt_path' => $receipt['relative_path'] ?? null,
                'amount_paid' => $amountPaid,
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'previous_paid_total' => $currentPaid,
                'current_paid_total' => $newPaidTotal,
                'remaining_amount' => max(0, round($expected - $newPaidTotal, 2)),
                'status' => $status,
            ]);

            $this->backup->storeFinancialSnapshot($tenantId, 'installment-payment', [
                'invoice_id' => $invoiceId,
                'payment_id' => $paymentId,
                'receipt_number' => $receipt['number'] ?? null,
                'receipt_path' => $receipt['relative_path'] ?? null,
                'amount_paid' => $amountPaid,
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'invoice' => $invoice,
                'current_paid_total' => $newPaidTotal,
            ]);

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_string($generatedReceiptAbsolutePath) && $generatedReceiptAbsolutePath !== '' && is_file($generatedReceiptAbsolutePath)) {
                @unlink($generatedReceiptAbsolutePath);
            }
            throw $e;
        }
    }

    public function createInstallmentPlan(int $tenantId, int $projectId, int $clientId, ?int $userId, array $data): int
    {
        if ($projectId <= 0) {
            throw new RuntimeException('Selecione um projeto.');
        }
        if ($clientId <= 0) {
            throw new RuntimeException('Cliente inválido para o projeto.');
        }

        if ($this->finance->findInstallmentPlanByProject($tenantId, $projectId) !== null) {
            throw new RuntimeException('Este projeto já possui um plano de parcelamento registrado no financeiro.');
        }
        if ($this->finance->listByProject($tenantId, $projectId) !== []) {
            throw new RuntimeException('Este projeto já possui parcelas geradas. Não é possível gerar novamente pelo financeiro.');
        }

        $totalRaw = trim((string)($data['valor_total'] ?? ''));
        $entryRaw = trim((string)($data['valor_entrada'] ?? ''));
        $installmentsCount = (int)($data['quantidade_parcelas'] ?? 0);
        $paymentTerms = Security::sanitizeString((string)($data['formas_pagamento'] ?? ''));

        $totalValue = Security::parseMoney($totalRaw);
        if ($totalValue === null) {
            throw new RuntimeException('Informe um valor total válido.');
        }
        $entryValue = $entryRaw !== '' ? Security::parseMoney($entryRaw) : null;
        if ($entryRaw !== '' && $entryValue === null) {
            throw new RuntimeException('Informe um valor de entrada válido.');
        }

        $entryDateRaw = trim((string)($data['data_entrada'] ?? ''));
        $entryDate = $entryDateRaw !== '' ? Security::parseDate($entryDateRaw) : null;
        if ($entryValue !== null && $entryValue > 0 && $entryDate === null) {
            throw new RuntimeException('Informe a data da entrada.');
        }

        $firstInstallmentDateRaw = trim((string)($data['data_primeira_parcela'] ?? ''));
        $firstInstallmentDate = $firstInstallmentDateRaw !== '' ? Security::parseDate($firstInstallmentDateRaw) : null;

        $calc = (new FinanceInstallmentCalculator())->calculate($totalValue, $entryValue !== null && $entryValue > 0 ? $entryValue : null, $installmentsCount);
        $remaining = (float)$calc['remaining_balance'];

        if ($remaining > 0 && $firstInstallmentDate === null) {
            throw new RuntimeException('Informe a data da primeira parcela.');
        }
        if ($entryValue !== null && $entryValue > 0 && $entryDate !== null && $firstInstallmentDate !== null && $firstInstallmentDate <= $entryDate) {
            throw new RuntimeException('A data da primeira parcela deve ser maior que a data da entrada.');
        }

        $pdo = DB::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $planId = $this->finance->createInstallmentPlan([
                'tenant_id' => $tenantId,
                'project_id' => $projectId,
                'valor_total' => $calc['total_value'],
                'valor_entrada' => $calc['entry_value'],
                'data_entrada' => $entryDate,
                'saldo_restante' => $calc['remaining_balance'],
                'quantidade_parcelas' => $calc['installments_count'],
                'valor_parcela' => $calc['installment_value'],
                'data_primeira_parcela' => $firstInstallmentDate,
                'formas_pagamento' => $paymentTerms,
                'created_by_user_id' => $userId,
            ]);

            $createdInvoices = [];

            if ($calc['entry_value'] !== null && (float)$calc['entry_value'] > 0) {
                $due = $entryDate ?? $firstInstallmentDate ?? date('Y-m-d');
                $status = $due < date('Y-m-d') ? 'overdue' : 'pending';
                $invoiceId = $this->finance->createInvoice([
                    'tenant_id' => $tenantId,
                    'client_id' => $clientId,
                    'project_id' => $projectId,
                    'code' => sprintf('FIN-%d-ENT-00', $projectId),
                    'description' => 'Entrada · Plano financeiro',
                    'amount_total' => (float)$calc['entry_value'],
                    'status' => $status,
                    'installment_type' => 'entry',
                    'installment_number' => 0,
                    'reference_month' => (int)substr($due, 5, 2),
                    'reference_year' => (int)substr($due, 0, 4),
                    'source_task_id' => null,
                    'payment_terms' => $paymentTerms,
                    'due_date' => $due,
                    'created_by_user_id' => $userId,
                ]);
                $this->finance->createCashFlowEntry([
                    'tenant_id' => $tenantId,
                    'client_id' => $clientId,
                    'project_id' => $projectId,
                    'invoice_id' => $invoiceId,
                    'type' => 'income',
                    'category' => 'project_installment',
                    'amount' => (float)$calc['entry_value'],
                    'entry_date' => $due,
                    'status' => $status === 'overdue' ? 'overdue' : 'pending',
                    'note' => 'Entrada do plano financeiro (ID ' . $planId . ')',
                    'created_by_user_id' => $userId,
                ]);
                $createdInvoices[] = $invoiceId;
            }

            if ($remaining > 0) {
                $baseDate = DateTimeImmutable::createFromFormat('Y-m-d', (string)$firstInstallmentDate);
                $baseDate = $baseDate === false ? new DateTimeImmutable((string)$firstInstallmentDate) : $baseDate;
                $accumulated = 0.0;
                for ($i = 1; $i <= (int)$calc['installments_count']; $i++) {
                    $due = $baseDate->add(new DateInterval('P' . ($i - 1) . 'M'))->format('Y-m-d');
                    $amount = $i === (int)$calc['installments_count']
                        ? round($remaining - $accumulated, 2)
                        : (float)$calc['installment_value'];
                    $accumulated = round($accumulated + $amount, 2);
                    $status = $due < date('Y-m-d') ? 'overdue' : 'pending';
                    $invoiceId = $this->finance->createInvoice([
                        'tenant_id' => $tenantId,
                        'client_id' => $clientId,
                        'project_id' => $projectId,
                        'code' => sprintf('FIN-%d-MEN-%02d', $projectId, $i),
                        'description' => 'Parcela ' . $i . ' · Plano financeiro',
                        'amount_total' => $amount,
                        'status' => $status,
                        'installment_type' => 'monthly',
                        'installment_number' => $i,
                        'reference_month' => (int)substr($due, 5, 2),
                        'reference_year' => (int)substr($due, 0, 4),
                        'source_task_id' => null,
                        'payment_terms' => $paymentTerms,
                        'due_date' => $due,
                        'created_by_user_id' => $userId,
                    ]);
                    $this->finance->createCashFlowEntry([
                        'tenant_id' => $tenantId,
                        'client_id' => $clientId,
                        'project_id' => $projectId,
                        'invoice_id' => $invoiceId,
                        'type' => 'income',
                        'category' => 'project_installment',
                        'amount' => $amount,
                        'entry_date' => $due,
                        'status' => $status === 'overdue' ? 'overdue' : 'pending',
                        'note' => 'Parcela ' . $i . ' do plano financeiro (ID ' . $planId . ')',
                        'created_by_user_id' => $userId,
                    ]);
                    $createdInvoices[] = $invoiceId;
                }
            }

            $this->audit->record($tenantId, $userId, 'installment_plan', $planId, 'finance.installment_plan.created', [
                'project_id' => $projectId,
                'client_id' => $clientId,
                'total_value' => $calc['total_value'],
                'entry_value' => $calc['entry_value'],
                'remaining_balance' => $calc['remaining_balance'],
                'installments_count' => $calc['installments_count'],
                'installment_value' => $calc['installment_value'],
                'entry_date' => $entryDate,
                'first_installment_date' => $firstInstallmentDate,
                'invoice_ids' => $createdInvoices,
            ]);

            $this->backup->storeFinancialSnapshot($tenantId, 'installment-plan', [
                'plan_id' => $planId,
                'project_id' => $projectId,
                'client_id' => $clientId,
                'values' => $calc,
                'entry_date' => $entryDate,
                'first_installment_date' => $firstInstallmentDate,
                'invoices' => $createdInvoices,
            ]);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $planId;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function ensurePaymentReceipt(int $tenantId, int $paymentId, ?int $userId): array
    {
        $payment = $this->finance->findPaymentById($tenantId, $paymentId);
        if ($payment === null) {
            throw new RuntimeException('Pagamento não encontrado.');
        }

        $existingPath = trim((string)($payment['receipt_path'] ?? ''));
        if ($existingPath !== '') {
            return [
                'number' => (string)($payment['receipt_number'] ?? ''),
                'relative_path' => $existingPath,
                'bytes' => (int)($payment['receipt_size_bytes'] ?? 0),
                'generated' => false,
            ];
        }

        $pdo = DB::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        $generatedReceiptAbsolutePath = null;

        try {
            $receipt = $this->receipts->generateForPayment($tenantId, $paymentId);
            $generatedReceiptAbsolutePath = (string)($receipt['absolute_path'] ?? null);
            $this->finance->attachPaymentReceipt(
                $tenantId,
                $paymentId,
                (string)$receipt['number'],
                (string)$receipt['relative_path'],
                (int)$receipt['bytes']
            );

            $this->audit->record($tenantId, $userId, 'payment', $paymentId, 'finance.receipt.generated', [
                'receipt_number' => $receipt['number'] ?? null,
                'receipt_path' => $receipt['relative_path'] ?? null,
                'receipt_size_bytes' => $receipt['bytes'] ?? null,
            ]);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return [
                'number' => (string)$receipt['number'],
                'relative_path' => (string)$receipt['relative_path'],
                'bytes' => (int)$receipt['bytes'],
                'generated' => true,
            ];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_string($generatedReceiptAbsolutePath) && $generatedReceiptAbsolutePath !== '' && is_file($generatedReceiptAbsolutePath)) {
                @unlink($generatedReceiptAbsolutePath);
            }
            throw $e;
        }
    }

    private function normalizePaymentMethod(string $value): string
    {
        $method = trim($value);
        if ($method === '') {
            return '';
        }

        $allowed = ['pix', 'boleto', 'transferencia', 'cartao', 'dinheiro', 'outro'];
        if (!in_array($method, $allowed, true)) {
            throw new RuntimeException('Método de pagamento inválido.');
        }

        return $method;
    }

    private function normalizeAnticipationAdjustmentMode(string $value): string
    {
        $mode = trim($value);
        if ($mode === '') {
            return 'none';
        }

        $allowed = ['none', 'discount', 'interest'];
        if (!in_array($mode, $allowed, true)) {
            throw new RuntimeException('Modo de ajuste da antecipação inválido.');
        }

        return $mode;
    }

    private function assertAnticipationSelectionBelongsToSingleClient(array $installments): void
    {
        $clientIds = array_values(array_unique(array_map(static fn(array $row): int => (int)($row['client_id'] ?? 0), $installments)));
        if (count($clientIds) !== 1 || (int)($clientIds[0] ?? 0) <= 0) {
            throw new RuntimeException('Selecione parcelas pertencentes ao mesmo cliente para processar a antecipação.');
        }
    }
}
