<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Helpers\Flash;
use App\Helpers\Security;
use App\Helpers\Path;
use App\Helpers\Session;
use App\Services\FinanceService;
use App\Services\FinanceService;
use App\Services\AuditLogService;
use App\Services\PaymentReceiptService;
use App\Repositories\FinanceRepository;

final class FinanceController
{
    public function index(Request $request): void
    {
        $this->renderIndexPage($request);
    }

    public function createPlan(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);

        try {
            $post = $request->all();
            (new FinanceService())->createInstallmentPlan(
                $tenantId,
                (int)($request->input('project_id') ?? 0),
                (int)($request->input('client_id') ?? 0),
                $userId > 0 ? $userId : null,
                [
                    'valor_total' => (string)($request->input('valor_total') ?? ''),
                    'valor_entrada' => (string)($request->input('valor_entrada') ?? ''),
                    'data_entrada' => (string)($request->input('data_entrada') ?? ''),
                    'data_primeira_parcela' => (string)($request->input('data_primeira_parcela') ?? ''),
                    'quantidade_parcelas' => (int)($request->input('quantidade_parcelas') ?? 0),
                    'formas_pagamento' => Security::sanitizeString((string)($request->input('formas_pagamento') ?? '')),
                    'installments' => is_array($post['installments'] ?? null) ? $post['installments'] : [],
                ]
            );
            Flash::set('ok', 'Plano de parcelamento criado e parcelas geradas com sucesso.');
        } catch (\Throwable $e) {
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }

        Response::redirect('/finance?month=' . (int)($request->input('redirect_month') ?? date('m')) . '&year=' . (int)($request->input('redirect_year') ?? date('Y')));
    }

    public function settle(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);

        try {
            (new FinanceService())->settleInstallment(
                $tenantId,
                (int)($request->input('invoice_id') ?? 0),
                $userId > 0 ? $userId : null,
                [
                    'amount_paid' => Security::parseMoney($request->input('amount_paid') ?? '0'),
                    'payment_date' => Security::parseDate($request->input('payment_date') ?? '') ?? '',
                    'payment_method' => Security::sanitizeString($request->input('payment_method') ?? ''),
                    'note' => Security::sanitizeString($request->input('note') ?? ''),
                ]
            );
            Flash::set('ok', 'Pagamento registrado com sucesso.');
        } catch (\Throwable $e) {
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }

        $month = max(1, min(12, (int)($request->input('redirect_month') ?? date('m'))));
        $year = max(2000, min(2100, (int)($request->input('redirect_year') ?? date('Y'))));
        Response::redirect('/finance?month=' . $month . '&year=' . $year);
    }

    public function simulateAnticipation(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $form = $this->buildAnticipationForm($request);

        try {
            $simulation = (new FinanceService())->simulateInstallmentAnticipation(
                $tenantId,
                $form['client_id'] > 0 ? $form['client_id'] : null,
                $form['project_id'] > 0 ? $form['project_id'] : null,
                $this->extractAnticipationInvoiceIds($request),
                [
                    'payment_date' => $form['payment_date'],
                    'payment_method' => $form['payment_method'],
                    'adjustment_mode' => $form['adjustment_mode'],
                    'adjustment_rate' => $form['adjustment_rate_value'],
                    'note' => $form['note'],
                ]
            );

            $this->renderIndexPage($request, $simulation, $form);
            return;
        } catch (\Throwable $e) {
            $this->renderIndexPage($request, null, $form, Security::sanitizeString($e->getMessage()));
            return;
        }
    }

    public function processAnticipation(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);
        $form = $this->buildAnticipationForm($request);

        try {
            if (($request->input('confirm_anticipation') ?? '') !== 'yes') {
                throw new \RuntimeException('Confirme a antecipação antes de processar.');
            }

            $result = (new FinanceService())->processInstallmentAnticipation(
                $tenantId,
                $form['client_id'] > 0 ? $form['client_id'] : null,
                $form['project_id'] > 0 ? $form['project_id'] : null,
                $userId > 0 ? $userId : null,
                $this->extractAnticipationInvoiceIds($request),
                [
                    'payment_date' => $form['payment_date'],
                    'payment_method' => $form['payment_method'],
                    'adjustment_mode' => $form['adjustment_mode'],
                    'adjustment_rate' => $form['adjustment_rate_value'],
                    'note' => $form['note'],
                ]
            );

            Flash::set(
                'ok',
                'Antecipação processada com sucesso para ' . (int)$result['selected_count'] . ' parcela(s), total de R$ '
                . number_format((float)$result['total_amount'], 2, ',', '.')
                . '.'
            );
        } catch (\Throwable $e) {
            Flash::set('error', Security::sanitizeString($e->getMessage()));
        }

        Response::redirect($this->buildFinanceReturnUrl($request, $form));
    }

    public function downloadReceipt(Request $request): void
    {
        $tenantId = (int)Session::get('tenant_id', 0);
        $userId = (int)Session::get('user_id', 0);
        $paymentId = (int)($request->route('id', '0') ?? '0');
        $month = max(1, min(12, (int)($request->query('month', date('m')) ?? date('m'))));
        $year = max(2000, min(2100, (int)($request->query('year', date('Y')) ?? date('Y'))));
        $returnToRaw = (string)($request->query('return_to', '') ?? '');
        $returnTo = (str_starts_with($returnToRaw, '/') && !str_starts_with($returnToRaw, '//'))
            ? $returnToRaw
            : ('/finance?month=' . $month . '&year=' . $year);

        try {
            if ($paymentId <= 0) {
                throw new \RuntimeException('Pagamento inválido para download do recibo.');
            }

            $service = new FinanceService();
            $service->ensurePaymentReceipt($tenantId, $paymentId, $userId > 0 ? $userId : null);

            $payment = (new FinanceRepository())->findPaymentById($tenantId, $paymentId);
            if ($payment === null) {
                throw new \RuntimeException('Pagamento não encontrado.');
            }

            $relativePath = trim((string)($payment['receipt_path'] ?? ''));
            if ($relativePath === '') {
                throw new \RuntimeException('Este pagamento ainda não possui recibo disponível.');
            }

            $normalized = str_replace('\\', '/', $relativePath);
            if (!str_starts_with($normalized, 'storage/recibos/')) {
                throw new \RuntimeException('Caminho de recibo inválido.');
            }

            $absolutePath = Path::base(str_replace('/', DIRECTORY_SEPARATOR, $normalized));
            if (!is_file($absolutePath)) {
                throw new \RuntimeException('Arquivo de recibo não encontrado em disco.');
            }

            $receiptNumber = trim((string)($payment['receipt_number'] ?? 'recibo-' . $paymentId));
            $filename = (new PaymentReceiptService())->buildDownloadFilename($receiptNumber);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($absolutePath));

            (new AuditLogService())->record(
                $tenantId,
                $userId > 0 ? $userId : null,
                'payment',
                $paymentId,
                'finance.receipt.downloaded',
                [
                    'receipt_number' => $payment['receipt_number'] ?? null,
                    'receipt_path' => $normalized,
                    'downloaded_at' => date('Y-m-d H:i:s'),
                ]
            );

            readfile($absolutePath);
            exit;
        } catch (\Throwable $e) {
            Flash::set('error', Security::sanitizeString($e->getMessage()));
            Response::redirect($returnTo);
        }
    }

    private function renderIndexPage(
        Request $request,
        ?array $anticipationSimulation = null,
        ?array $anticipationForm = null,
        ?string $errorOverride = null
    ): void {
        $tenantId = (int)Session::get('tenant_id', 0);
        $service = new FinanceService();
        $repo = new FinanceRepository();
        $month = max(1, min(12, (int)($request->query('month', $request->input('redirect_month', date('m')) ?? date('m')) ?? date('m'))));
        $year = max(2000, min(2100, (int)($request->query('year', $request->input('redirect_year', date('Y')) ?? date('Y')) ?? date('Y'))));
        $summary = [
            'income' => 0,
            'balance' => 0,
            'paid' => 0,
            'pending' => 0,
            'overdue' => 0,
        ];
        $entries = [];
        $projects = [];
        $ok = Flash::get('ok');
        $error = $errorOverride ?? Flash::get('error');
        $filter = [
            'month' => $month,
            'year' => $year,
        ];
        $anticipationForm ??= [
            'client_id' => max(0, (int)($request->query('anticipation_client_id', '0') ?? '0')),
            'project_id' => max(0, (int)($request->query('anticipation_project_id', '0') ?? '0')),
            'payment_date' => date('Y-m-d'),
            'payment_method' => 'pix',
            'adjustment_mode' => 'none',
            'adjustment_rate' => '0,00',
            'adjustment_rate_value' => 0.0,
            'note' => '',
            'invoice_ids' => [],
        ];

        try {
            $summary = $service->summary($tenantId, $month, $year);
            $entries = $service->listByTenant($tenantId, $month, $year);
            $projects = $repo->projectsForInstallmentPlanning($tenantId);
            $anticipation = $service->anticipationContext(
                $tenantId,
                $anticipationForm['client_id'] > 0 ? (int)$anticipationForm['client_id'] : null,
                $anticipationForm['project_id'] > 0 ? (int)$anticipationForm['project_id'] : null
            );
        } catch (\Throwable $e) {
            $message = 'Não foi possível carregar o financeiro agora: ' . Security::sanitizeString($e->getMessage());
            $error = $error !== null && $error !== '' ? $error . ' ' . $message : $message;
            $anticipation = [
                'clients' => [],
                'projects' => [],
                'candidates' => [],
                'history' => [],
                'filters' => [
                    'client_id' => $anticipationForm['client_id'],
                    'project_id' => $anticipationForm['project_id'],
                ],
            ];
        }

        View::render('finance/index', [
            'summary' => $summary,
            'entries' => $entries,
            'projects' => $projects,
            'tenantName' => (string)Session::get('tenant_name', 'TRAXTER'),
            'role' => (string)Session::get('role', 'user'),
            'ok' => $ok,
            'filter' => $filter,
            'error' => $error,
            'anticipation' => $anticipation,
            'anticipationSimulation' => $anticipationSimulation,
            'anticipationForm' => $anticipationForm,
        ]);
    }

    private function buildAnticipationForm(Request $request): array
    {
        $post = $request->all();
        $rateRaw = Security::sanitizeString($request->input('adjustment_rate') ?? '0');

        return [
            'client_id' => max(0, (int)($request->input('client_id') ?? 0)),
            'project_id' => max(0, (int)($request->input('project_id') ?? 0)),
            'payment_date' => Security::parseDate($request->input('payment_date') ?? '') ?? '',
            'payment_method' => Security::sanitizeString($request->input('payment_method') ?? ''),
            'adjustment_mode' => Security::sanitizeString($request->input('adjustment_mode') ?? 'none'),
            'adjustment_rate' => $rateRaw === '' ? '0,00' : $rateRaw,
            'adjustment_rate_value' => Security::parseMoney($rateRaw),
            'note' => Security::sanitizeString($request->input('note') ?? ''),
            'invoice_ids' => is_array($post['invoice_ids'] ?? null) ? array_values($post['invoice_ids']) : [],
        ];
    }

    private function extractAnticipationInvoiceIds(Request $request): array
    {
        $post = $request->all();
        if (!is_array($post['invoice_ids'] ?? null)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn(mixed $value): int => (int)$value, $post['invoice_ids']),
            static fn(int $value): bool => $value > 0
        ));
    }

    private function buildFinanceReturnUrl(Request $request, array $form): string
    {
        $month = max(1, min(12, (int)($request->input('redirect_month') ?? date('m'))));
        $year = max(2000, min(2100, (int)($request->input('redirect_year') ?? date('Y'))));
        $query = '/finance?month=' . $month . '&year=' . $year;

        if ((int)$form['client_id'] > 0) {
            $query .= '&anticipation_client_id=' . (int)$form['client_id'];
        }
        if ((int)$form['project_id'] > 0) {
            $query .= '&anticipation_project_id=' . (int)$form['project_id'];
        }

        return $query;
    }
}
