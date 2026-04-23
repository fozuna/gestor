<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\DB;
use App\Helpers\Security;
use App\Repositories\BudgetRepository;
use RuntimeException;

final class BudgetService
{
    public function __construct(
        private readonly BudgetRepository $budgets = new BudgetRepository(),
        private readonly ClientService $clients = new ClientService(),
        private readonly ProjectService $projects = new ProjectService(),
        private readonly FinanceInstallmentCalculator $calculator = new FinanceInstallmentCalculator(),
        private readonly InstallmentPlanner $planner = new InstallmentPlanner(),
        private readonly AuditLogService $audit = new AuditLogService(),
        private readonly BrandLogoService $logos = new BrandLogoService(),
        private readonly BudgetProposalService $proposal = new BudgetProposalService()
    ) {
    }

    public function paginatedList(int $tenantId, array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(20, max(5, (int)($filters['per_page'] ?? 10)));
        $search = trim((string)($filters['search'] ?? ''));
        $status = trim((string)($filters['status'] ?? ''));
        $offset = ($page - 1) * $perPage;

        $items = $this->budgets->paginatedListByTenant($tenantId, $search, $status, $perPage, $offset);
        $total = $this->budgets->countByTenantFilters($tenantId, $search, $status);

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

    public function detail(int $tenantId, int $budgetId): ?array
    {
        return $this->budgets->findById($tenantId, $budgetId);
    }

    public function create(int $tenantId, ?int $userId, array $data): int
    {
        $payload = $this->normalizePayload($tenantId, $data, false, null);
        $id = $this->budgets->create($tenantId, $userId, $payload);
        $this->audit->record($tenantId, $userId, 'orcamento', $id, 'budget.created', [
            'client_id' => $payload['client_id'],
            'status' => $payload['status'],
            'nome_proposta' => $payload['nome_proposta'],
            'valor_total' => $payload['valor_total'],
        ]);
        return $id;
    }

    public function update(int $tenantId, int $budgetId, ?int $userId, array $data): void
    {
        $current = $this->budgets->findById($tenantId, $budgetId);
        if ($current === null) {
            throw new RuntimeException('Orçamento não encontrado.');
        }
        if ((string)$current['status'] === 'aprovado' || (int)($current['projeto_id'] ?? 0) > 0) {
            throw new RuntimeException('Orçamento aprovado não pode ser editado.');
        }

        $payload = $this->normalizePayload($tenantId, $data, true, $current);
        $this->guardCriticalEditionWhenSent($current, $payload);
        $this->budgets->update($tenantId, $budgetId, $payload);
        $this->audit->record($tenantId, $userId, 'orcamento', $budgetId, 'budget.updated', [
            'status' => $payload['status'],
            'nome_proposta' => $payload['nome_proposta'],
        ]);
    }

    public function changeStatus(int $tenantId, int $budgetId, string $status, ?int $userId): void
    {
        $target = $this->normalizeStatus($status);
        $budget = $this->budgets->findById($tenantId, $budgetId);
        if ($budget === null) {
            throw new RuntimeException('Orçamento não encontrado.');
        }

        $current = (string)($budget['status'] ?? 'rascunho');
        if ($current === 'aprovado') {
            throw new RuntimeException('Este orçamento já foi aprovado.');
        }
        if ($target === 'aprovado') {
            $this->approveBudget($tenantId, $budget, $userId);
            return;
        }

        $this->budgets->setStatus($tenantId, $budgetId, $target);
        $this->audit->record($tenantId, $userId, 'orcamento', $budgetId, 'budget.status.changed', [
            'from' => $current,
            'to' => $target,
        ]);
    }

    public function buildProposalPreview(int $tenantId, int $budgetId, string $tenantName, string $presentationText): array
    {
        $budget = $this->budgets->findById($tenantId, $budgetId);
        if ($budget === null) {
            throw new RuntimeException('Orçamento não encontrado.');
        }

        $sections = $this->proposal->buildSections(
            $budget,
            (string)($budget['client_name'] ?? 'Cliente'),
            $tenantName,
            $presentationText
        );
        return [
            'budget' => $budget,
            'sections' => $sections,
            'text' => $this->proposal->renderText($sections),
        ];
    }

    public function exportProposalText(int $tenantId, int $budgetId, string $tenantName, string $presentationText): array
    {
        $preview = $this->buildProposalPreview($tenantId, $budgetId, $tenantName, $presentationText);
        $name = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string)($preview['budget']['nome_proposta'] ?? 'proposta'))) ?: 'proposta';
        return [
            'filename' => 'proposta-' . trim($name, '-') . '.txt',
            'content' => $preview['text'],
        ];
    }

    public function exportProposalPdf(int $tenantId, int $budgetId, string $tenantName, string $presentationText): array
    {
        $preview = $this->buildProposalPreview($tenantId, $budgetId, $tenantName, $presentationText);
        $logo = extension_loaded('gd') ? $this->logos->pdfLogoDataUriForBackground($tenantName, '#FFFFFF') : '';
        $html = $this->proposal->renderHtml($preview['sections'], $logo);
        $pdf = $this->proposal->renderPdfBinary($html);
        $name = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string)($preview['budget']['nome_proposta'] ?? 'proposta'))) ?: 'proposta';
        return [
            'filename' => 'proposta-' . trim($name, '-') . '.pdf',
            'content' => $pdf,
        ];
    }

    public function calculateFinancialSnapshot(float $totalValue, ?float $entryValue, int $installmentsCount): array
    {
        return $this->calculator->calculate($totalValue, $entryValue, $installmentsCount);
    }

    private function approveBudget(int $tenantId, array $budget, ?int $userId): void
    {
        if ((int)($budget['projeto_id'] ?? 0) > 0) {
            throw new RuntimeException('Projeto já gerado para este orçamento.');
        }
        if ((int)($budget['client_id'] ?? 0) <= 0) {
            throw new RuntimeException('Não é possível aprovar orçamento sem cliente.');
        }
        if ((float)($budget['valor_total'] ?? 0) <= 0) {
            throw new RuntimeException('Não é possível aprovar orçamento sem valor total.');
        }

        $remaining = round((float)$budget['valor_total'] - (float)($budget['valor_entrada'] ?? 0), 2);
        $count = (int)($budget['quantidade_parcelas'] ?? 0);
        $firstDate = (string)($budget['data_primeira_parcela'] ?? '');

        $installments = [];
        if ($remaining > 0) {
            $installments = $this->planner->suggestInstallments($remaining, $count, $firstDate);
        }

        $pdo = DB::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $projectId = $this->projects->create(
                $tenantId,
                (int)$budget['client_id'],
                $userId,
                [
                    'name' => (string)$budget['nome_proposta'],
                    'description' => (string)($budget['descricao'] ?? ''),
                    'status' => 'active',
                    'start_date' => '',
                    'due_date' => '',
                    'contract_value' => (string)number_format((float)$budget['valor_total'], 2, ',', '.'),
                    'entry_amount' => (string)number_format((float)($budget['valor_entrada'] ?? 0), 2, ',', '.'),
                    'payment_terms' => (string)($budget['condicoes_pagamento'] ?? ''),
                    'installments_count' => $count,
                    'first_installment_date' => (string)$firstDate,
                    'installments' => array_map(static fn(array $row): array => [
                        'due_date' => (string)$row['due_date'],
                        'amount' => (string)number_format((float)$row['amount_total'], 2, ',', '.'),
                    ], $installments),
                ]
            );

            $this->budgets->setApprovedWithProject($tenantId, (int)$budget['id'], $projectId);
            $this->audit->record($tenantId, $userId, 'orcamento', (int)$budget['id'], 'budget.approved', [
                'project_id' => $projectId,
                'client_id' => (int)$budget['client_id'],
                'valor_total' => (float)$budget['valor_total'],
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

    private function normalizePayload(int $tenantId, array $data, bool $isUpdate, ?array $current): array
    {
        $clientId = (int)($data['client_id'] ?? 0);
        $nome = trim((string)($data['nome_proposta'] ?? ''));
        $descricao = trim((string)($data['descricao'] ?? ''));
        $status = $this->normalizeStatus((string)($data['status'] ?? 'rascunho'));
        $dataValidade = Security::parseDate((string)($data['data_validade'] ?? '')) ?? '';
        $primeiraParcela = Security::parseDate((string)($data['data_primeira_parcela'] ?? '')) ?? '';
        $condicoes = trim((string)($data['condicoes_pagamento'] ?? ''));

        $total = Security::parseMoney((string)($data['valor_total'] ?? '0'));
        $entryRaw = trim((string)($data['valor_entrada'] ?? ''));
        $entry = $entryRaw !== '' ? Security::parseMoney($entryRaw) : null;
        $count = max(0, (int)($data['quantidade_parcelas'] ?? 0));

        if ($clientId <= 0) {
            throw new RuntimeException('Selecione um cliente para o orçamento.');
        }
        if ($this->clients->findById($tenantId, $clientId) === null) {
            throw new RuntimeException('Cliente inválido para o orçamento.');
        }
        if ($nome === '') {
            throw new RuntimeException('Informe o nome da proposta.');
        }
        if ($total <= 0) {
            throw new RuntimeException('Informe um valor total válido.');
        }

        $calc = $this->calculateFinancialSnapshot($total, $entry, $count);
        if ((float)$calc['remaining_balance'] > 0 && $primeiraParcela === '') {
            throw new RuntimeException('Informe a data da primeira parcela.');
        }

        return [
            'client_id' => $clientId,
            'nome_proposta' => $nome,
            'descricao' => $descricao,
            'valor_total' => $calc['total_value'],
            'valor_entrada' => $calc['entry_value'],
            'quantidade_parcelas' => $calc['installments_count'],
            'valor_parcela' => $calc['installment_value'],
            'data_primeira_parcela' => $primeiraParcela,
            'condicoes_pagamento' => $condicoes,
            'status' => $status,
            'data_validade' => $dataValidade,
        ];
    }

    private function guardCriticalEditionWhenSent(array $current, array $payload): void
    {
        if ((string)($current['status'] ?? 'rascunho') !== 'enviado') {
            return;
        }
        $critical = [
            'client_id',
            'nome_proposta',
            'descricao',
            'valor_total',
            'valor_entrada',
            'quantidade_parcelas',
            'valor_parcela',
            'data_primeira_parcela',
            'condicoes_pagamento',
            'data_validade',
        ];
        foreach ($critical as $field) {
            $left = (string)($current[$field] ?? '');
            $right = (string)($payload[$field] ?? '');
            if ($left !== $right) {
                throw new RuntimeException('Orçamento enviado bloqueia edição de campos críticos.');
            }
        }
    }

    private function normalizeStatus(string $status): string
    {
        $value = trim($status);
        if ($value === '') {
            return 'rascunho';
        }
        if (!in_array($value, ['rascunho', 'enviado', 'aprovado', 'recusado'], true)) {
            throw new RuntimeException('Status de orçamento inválido.');
        }
        return $value;
    }
}
