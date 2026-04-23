<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\DB;
use App\Helpers\Security;
use App\Repositories\FinanceRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use RuntimeException;

final class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projects = new ProjectRepository(),
        private readonly FinanceRepository $finance = new FinanceRepository(),
        private readonly TaskRepository $tasks = new TaskRepository(),
        private readonly ProjectFinancialGuard $financialGuard = new ProjectFinancialGuard(),
        private readonly InstallmentPlanner $planner = new InstallmentPlanner(),
        private readonly AuditLogService $audit = new AuditLogService(),
        private readonly BackupService $backup = new BackupService()
    ) {
    }

    public function listByTenant(int $tenantId): array
    {
        $items = $this->projects->listByTenant($tenantId);
        return array_map(fn(array $project): array => $this->applyFinancialGuard($project), $items);
    }

    public function paginatedList(int $tenantId, array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(20, max(5, (int)($filters['per_page'] ?? 10)));
        $search = trim((string)($filters['search'] ?? ''));
        $status = trim((string)($filters['status'] ?? ''));
        $offset = ($page - 1) * $perPage;

        $items = $this->projects->paginatedListByTenant($tenantId, $search, $status, $perPage, $offset);
        $total = $this->projects->countByTenantFilters($tenantId, $search, $status);

        $items = array_map(fn(array $project): array => $this->applyFinancialGuard($project), $items);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => max(1, (int)ceil($total / $perPage)),
            ],
        ];
    }

    public function optionsByTenant(int $tenantId): array
    {
        return $this->projects->optionsByTenant($tenantId);
    }

    public function listByClient(int $tenantId, int $clientId): array
    {
        $items = $this->projects->listByClient($tenantId, $clientId);
        return array_map(fn(array $project): array => $this->applyFinancialGuard($project), $items);
    }

    public function detail(int $tenantId, int $projectId, ?int $userId = null): ?array
    {
        $project = $this->projects->findById($tenantId, $projectId);
        if ($project === null) {
            return null;
        }
        $project = $this->applyFinancialGuard($project);
        if (($project['_financial_guard_changed'] ?? false) === true) {
            $this->audit->record($tenantId, $userId, 'project', $projectId, 'project.financial_totals.clamped', [
                'contract_value' => $project['contract_value'],
                'raw_paid_amount' => $project['_raw_paid_amount'] ?? 0,
                'raw_pending_amount' => $project['_raw_pending_amount'] ?? 0,
                'paid_amount' => $project['paid_amount'],
                'pending_amount' => $project['pending_amount'],
            ]);
        }
        unset($project['_financial_guard_changed'], $project['_raw_paid_amount'], $project['_raw_pending_amount']);

        $installments = $this->finance->listByProject($tenantId, $projectId);
        $tasks = $this->tasks->listByProject($tenantId, $projectId);

        return [
            'project' => $project,
            'installments' => $installments,
            'tasks' => $tasks,
            'billable_tasks_total' => array_reduce(
                $tasks,
                static fn(float $carry, array $task): float => $carry + (float)($task['billable_amount'] ?? 0),
                0.0
            ),
        ];
    }

    private function applyFinancialGuard(array $project): array
    {
        $normalized = $this->financialGuard->normalize(
            (float)($project['contract_value'] ?? 0),
            (float)($project['paid_amount'] ?? 0),
            (float)($project['pending_amount'] ?? 0)
        );

        $project['contract_value'] = $normalized['contract_value'];
        $project['paid_amount'] = $normalized['paid_amount'];
        $project['pending_amount'] = $normalized['pending_amount'];
        $project['_financial_guard_changed'] = $normalized['changed'];
        $project['_raw_paid_amount'] = $normalized['raw_paid_amount'];
        $project['_raw_pending_amount'] = $normalized['raw_pending_amount'];

        return $project;
    }

    public function createBasic(int $tenantId, int $clientId, ?int $userId, array $data): int
    {
        $payload = $this->normalizeBasicPayload($tenantId, 0, $clientId, $data, false);
        $projectId = $this->projects->create($tenantId, $clientId, $userId, $payload);
        $this->audit->record($tenantId, $userId, 'project', $projectId, 'project.created', [
            'client_id' => $clientId,
            'status' => $payload['status'],
            'name' => $payload['name'],
        ]);
        return $projectId;
    }

    public function updateBasic(int $tenantId, int $projectId, int $clientId, ?int $userId, array $data): void
    {
        $project = $this->projects->findById($tenantId, $projectId);
        if ($project === null) {
            throw new RuntimeException('Projeto não encontrado.');
        }

        $payload = $this->normalizeBasicPayload($tenantId, $projectId, $clientId, $data, true);
        $this->projects->update($tenantId, $projectId, $clientId, $payload);
        $this->audit->record($tenantId, $userId, 'project', $projectId, 'project.updated', [
            'client_id' => $clientId,
            'status' => $payload['status'],
            'name' => $payload['name'],
        ]);
    }

    public function deleteBasic(int $tenantId, int $projectId, ?int $userId): void
    {
        $project = $this->projects->findById($tenantId, $projectId);
        if ($project === null) {
            throw new RuntimeException('Projeto não encontrado.');
        }

        if ($this->projects->hasSettledFinancialRecords($tenantId, $projectId)) {
            throw new RuntimeException('Não é permitido excluir projeto com valores financeiros já liquidados.');
        }

        $stats = $this->projects->financialDeletionStats($tenantId, $projectId);

        $pdo = DB::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $deleted = $this->projects->delete($tenantId, $projectId);
            if ($deleted !== 1) {
                throw new RuntimeException('Falha ao excluir o projeto de forma atômica.');
            }

            $this->audit->record($tenantId, $userId, 'project', $projectId, 'project.deleted', [
                'name' => $project['name'] ?? '',
                'financial_removed' => $stats,
                'financial_total_removed' => array_sum($stats),
            ]);

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function create(int $tenantId, int $clientId, ?int $userId, array $data): int
    {
        $name = trim((string)($data['name'] ?? ''));
        $status = (string)($data['status'] ?? 'active');
        $contractValue = Security::parseMoney((string)($data['contract_value'] ?? '0'));
        if ($contractValue === null) {
            throw new RuntimeException('Informe um valor total válido para o projeto.');
        }
        $entryAmount = Security::parseMoney((string)($data['entry_amount'] ?? '0')) ?? 0.0;
        $paymentTerms = trim((string)($data['payment_terms'] ?? ''));
        $startDate = trim((string)($data['start_date'] ?? ''));
        $dueDate = trim((string)($data['due_date'] ?? ''));
        $firstInstallmentDate = trim((string)($data['first_installment_date'] ?? ''));
        $installmentsCount = (int)($data['installments_count'] ?? 0);
        $rawInstallments = is_array($data['installments'] ?? null) ? $data['installments'] : [];

        if ($clientId <= 0) {
            throw new RuntimeException('Selecione um cliente para o projeto.');
        }

        if ($name === '') {
            throw new RuntimeException('Informe o nome do projeto.');
        }

        if (!in_array($status, ['active', 'paused', 'done'], true)) {
            throw new RuntimeException('Status de projeto inválido.');
        }

        if ($this->projects->existsForTenant($tenantId, $name)) {
            throw new RuntimeException('Já existe um projeto com esse nome neste workspace.');
        }

        $normalizedInstallments = array_values(array_filter(array_map(
            static fn(array $row): array => [
                'due_date' => Security::parseDate((string)($row['due_date'] ?? '')),
                'amount' => Security::parseMoney((string)($row['amount'] ?? '0')) ?? 0.0,
            ],
            array_filter($rawInstallments, static fn($row): bool => is_array($row))
        ), static fn(array $row): bool => $row['due_date'] !== null && (float)$row['amount'] > 0));

        $normalizedFirstInstallmentDate = Security::parseDate($firstInstallmentDate);
        $remaining = round($contractValue - $entryAmount, 2);
        if ($remaining > 0 && $normalizedInstallments === []) {
            if ($installmentsCount <= 0) {
                throw new RuntimeException('Informe a quantidade de parcelas para gerar o plano financeiro.');
            }
            $suggested = $this->planner->suggestInstallments($remaining, $installmentsCount, $normalizedFirstInstallmentDate);
            $normalizedInstallments = array_map(
                static fn(array $row): array => ['due_date' => (string)$row['due_date'], 'amount' => (float)$row['amount_total']],
                $suggested
            );
        }

        $plan = $this->planner->buildPlan(
            $contractValue,
            $entryAmount,
            $normalizedFirstInstallmentDate,
            $normalizedInstallments
        );

        $pdo = DB::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $projectId = $this->projects->create($tenantId, $clientId, $userId, [
                'name' => $name,
                'description' => trim((string)($data['description'] ?? '')),
                'status' => $status,
                'start_date' => Security::parseDate($startDate) ?? '',
                'due_date' => Security::parseDate($dueDate) ?? '',
                'contract_value' => $contractValue,
                'payment_terms' => $paymentTerms,
                'entry_amount' => $entryAmount,
                'installments_count' => count(array_filter($plan, static fn(array $row): bool => $row['installment_type'] === 'monthly')),
                'first_installment_date' => Security::parseDate($firstInstallmentDate) ?? '',
            ]);

            foreach ($plan as $installment) {
                $invoiceStatus = $installment['due_date'] < date('Y-m-d') ? 'overdue' : 'pending';
                $invoiceId = $this->finance->createInvoice([
                    'tenant_id' => $tenantId,
                    'client_id' => $clientId,
                    'project_id' => $projectId,
                    'code' => sprintf('PRJ-%d-%s-%02d', $projectId, strtoupper(substr($installment['installment_type'], 0, 3)), $installment['installment_number']),
                    'description' => $installment['label'] . ' · ' . $name,
                    'amount_total' => $installment['amount_total'],
                    'status' => $invoiceStatus,
                    'installment_type' => $installment['installment_type'],
                    'installment_number' => $installment['installment_number'],
                    'reference_month' => $installment['reference_month'],
                    'reference_year' => $installment['reference_year'],
                    'source_task_id' => null,
                    'payment_terms' => $paymentTerms,
                    'due_date' => $installment['due_date'],
                    'created_by_user_id' => $userId,
                ]);

                $this->finance->createCashFlowEntry([
                    'tenant_id' => $tenantId,
                    'client_id' => $clientId,
                    'project_id' => $projectId,
                    'invoice_id' => $invoiceId,
                    'type' => 'income',
                    'category' => 'project_installment',
                    'amount' => $installment['amount_total'],
                    'entry_date' => $installment['due_date'],
                    'status' => $invoiceStatus === 'overdue' ? 'overdue' : 'pending',
                    'note' => $installment['label'] . ' vinculada ao projeto ' . $name,
                    'created_by_user_id' => $userId,
                ]);
            }

            $this->audit->record($tenantId, $userId, 'project', $projectId, 'project.financial_plan.created', [
                'project_name' => $name,
                'contract_value' => $contractValue,
                'entry_amount' => $entryAmount,
                'installments_count' => count($plan),
            ]);

            $this->backup->storeFinancialSnapshot($tenantId, 'project-plan', [
                'project_id' => $projectId,
                'client_id' => $clientId,
                'contract_value' => $contractValue,
                'installments' => $plan,
            ]);

            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $projectId;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function ensureFinancialPlan(int $tenantId, int $projectId, int $clientId, ?int $userId, array $data): bool
    {
        $existing = $this->finance->listByProject($tenantId, $projectId);
        if ($existing !== []) {
            return false;
        }

        $project = $this->projects->findById($tenantId, $projectId);
        if ($project === null) {
            throw new RuntimeException('Projeto não encontrado.');
        }

        $name = (string)($project['name'] ?? '');
        $contractValue = Security::parseMoney((string)($data['contract_value'] ?? '0'));
        if ($contractValue === null) {
            throw new RuntimeException('Informe um valor total válido para o projeto.');
        }

        $entryAmount = Security::parseMoney((string)($data['entry_amount'] ?? '0')) ?? 0.0;
        $paymentTerms = trim((string)($data['payment_terms'] ?? ''));
        $firstInstallmentDate = Security::parseDate((string)($data['first_installment_date'] ?? ''));
        $installmentsCount = (int)($data['installments_count'] ?? 0);
        $rawInstallments = is_array($data['installments'] ?? null) ? $data['installments'] : [];

        $normalizedInstallments = array_values(array_filter(array_map(
            static fn(array $row): array => [
                'due_date' => Security::parseDate((string)($row['due_date'] ?? '')),
                'amount' => Security::parseMoney((string)($row['amount'] ?? '0')) ?? 0.0,
            ],
            array_filter($rawInstallments, static fn($row): bool => is_array($row))
        ), static fn(array $row): bool => $row['due_date'] !== null && (float)$row['amount'] > 0));

        $remaining = round($contractValue - $entryAmount, 2);
        if ($remaining > 0 && $normalizedInstallments === []) {
            if ($installmentsCount <= 0) {
                throw new RuntimeException('Informe a quantidade de parcelas para gerar o plano financeiro.');
            }
            $suggested = $this->planner->suggestInstallments($remaining, $installmentsCount, $firstInstallmentDate);
            $normalizedInstallments = array_map(
                static fn(array $row): array => ['due_date' => (string)$row['due_date'], 'amount' => (float)$row['amount_total']],
                $suggested
            );
        }

        $plan = $this->planner->buildPlan($contractValue, $entryAmount, $firstInstallmentDate, $normalizedInstallments);

        $pdo = DB::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            foreach ($plan as $installment) {
                $invoiceStatus = $installment['due_date'] < date('Y-m-d') ? 'overdue' : 'pending';
                $invoiceId = $this->finance->createInvoice([
                    'tenant_id' => $tenantId,
                    'client_id' => $clientId,
                    'project_id' => $projectId,
                    'code' => sprintf('PRJ-%d-%s-%02d', $projectId, strtoupper(substr($installment['installment_type'], 0, 3)), $installment['installment_number']),
                    'description' => $installment['label'] . ' · ' . $name,
                    'amount_total' => $installment['amount_total'],
                    'status' => $invoiceStatus,
                    'installment_type' => $installment['installment_type'],
                    'installment_number' => $installment['installment_number'],
                    'reference_month' => $installment['reference_month'],
                    'reference_year' => $installment['reference_year'],
                    'source_task_id' => null,
                    'payment_terms' => $paymentTerms,
                    'due_date' => $installment['due_date'],
                    'created_by_user_id' => $userId,
                ]);

                $this->finance->createCashFlowEntry([
                    'tenant_id' => $tenantId,
                    'client_id' => $clientId,
                    'project_id' => $projectId,
                    'invoice_id' => $invoiceId,
                    'type' => 'income',
                    'category' => 'project_installment',
                    'amount' => $installment['amount_total'],
                    'entry_date' => $installment['due_date'],
                    'status' => $invoiceStatus === 'overdue' ? 'overdue' : 'pending',
                    'note' => $installment['label'] . ' vinculada ao projeto ' . $name,
                    'created_by_user_id' => $userId,
                ]);
            }

            $this->audit->record($tenantId, $userId, 'project', $projectId, 'project.financial_plan.created', [
                'project_name' => $name,
                'contract_value' => $contractValue,
                'entry_amount' => $entryAmount,
                'installments_count' => count($plan),
                'source' => 'ensure',
            ]);

            $this->backup->storeFinancialSnapshot($tenantId, 'project-plan', [
                'project_id' => $projectId,
                'client_id' => $clientId,
                'contract_value' => $contractValue,
                'installments' => $plan,
            ]);

            if ($ownsTransaction) {
                $pdo->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function normalizeBasicPayload(int $tenantId, int $projectId, int $clientId, array $data, bool $isUpdate): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $status = (string)($data['status'] ?? 'active');
        $startDate = Security::parseDate((string)($data['start_date'] ?? '')) ?? '';
        $dueDate = Security::parseDate((string)($data['due_date'] ?? '')) ?? '';

        if ($clientId <= 0) {
            throw new RuntimeException('Selecione um cliente para o projeto.');
        }
        if ($name === '') {
            throw new RuntimeException('Informe o nome do projeto.');
        }
        if (!in_array($status, ['active', 'paused', 'done'], true)) {
            throw new RuntimeException('Status de projeto inválido.');
        }
        $exists = $isUpdate
            ? $this->projects->existsForTenantExcludingId($tenantId, $name, $projectId)
            : $this->projects->existsForTenant($tenantId, $name);
        if ($exists) {
            throw new RuntimeException('Já existe um projeto com esse nome neste workspace.');
        }

        return [
            'name' => $name,
            'description' => $description,
            'status' => $status,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'contract_value' => Security::parseMoney((string)($data['contract_value'] ?? '0')),
            'payment_terms' => trim((string)($data['payment_terms'] ?? '')),
            'entry_amount' => Security::parseMoney((string)($data['entry_amount'] ?? '0')),
            'installments_count' => max(0, (int)($data['installments_count'] ?? 0)),
            'first_installment_date' => Security::parseDate((string)($data['first_installment_date'] ?? '')) ?? '',
        ];
    }
}
