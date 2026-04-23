<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Security;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class ClientTasksPdfReportService
{
    public function __construct(
        private readonly ClientService $clients = new ClientService(),
        private readonly TaskService $tasks = new TaskService(),
        private readonly BrandLogoService $logos = new BrandLogoService()
    ) {
    }

    public function generateForClient(
        int $tenantId,
        int $clientId,
        string $startDate,
        string $endDate,
        string $tenantName,
        ?string $logoPath = null
    ): array {
        $client = $this->clients->findById($tenantId, $clientId);
        if ($client === null) {
            throw new RuntimeException('Cliente não encontrado para exportação.');
        }

        $tasks = $this->tasks->listByClient($tenantId, $clientId, $startDate, $endDate);
        $payload = $this->buildReportPayload($client, $tasks, $startDate, $endDate, $tenantName, $logoPath);
        $html = $this->renderHtml($payload);
        $pdfBinary = $this->renderPdfBinaryFromHtml($html);

        $clientName = (string)($client['name'] ?? 'cliente');
        $safeClientName = preg_replace('/[^a-z0-9]+/i', '-', strtolower($clientName)) ?: 'cliente';
        $filename = 'relatorio-tarefas-' . trim($safeClientName, '-') . '-' . Security::now()->format('Ymd-His') . '.pdf';

        return [
            'filename' => $filename,
            'content' => $pdfBinary,
            'metrics' => $payload['metrics'],
            'tasks_count' => count($payload['rows']),
        ];
    }

    public function buildReportPayload(
        array $client,
        array $tasks,
        string $startDate,
        string $endDate,
        string $tenantName,
        ?string $logoPath = null,
        ?string $generatedAt = null
    ): array {
        $today = Security::now()->format('Y-m-d');
        $rows = [];

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }
            $statusData = $this->normalizeStatusAndProgress($task, $today);
            $rows[] = [
                'name' => (string)($task['title'] ?? 'Sem título'),
                'description' => (string)($task['description'] ?? ''),
                'start_date' => $this->formatDate((string)($task['created_at'] ?? '')),
                'due_date' => $this->formatDate((string)($task['due_date'] ?? '')),
                'status_label' => $statusData['label'],
                'status_code' => $statusData['code'],
                'status_bg' => $statusData['bg'],
                'status_color' => $statusData['color'],
                'progress' => $statusData['progress'],
                'assignee' => (string)($task['assignee_name'] ?? 'Não atribuído'),
            ];
        }

        $total = count($rows);
        $completed = count(array_filter($rows, static fn(array $row): bool => $row['status_code'] === 'done'));
        $overdue = count(array_filter($rows, static fn(array $row): bool => $row['status_code'] === 'overdue'));
        $completionRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;

        return [
            'company' => [
                'name' => $tenantName,
                'logo_data_uri' => $this->dompdfSupportsImages()
                    ? $this->logos->pdfLogoDataUriForBackground($tenantName, '#FFFFFF', $logoPath)
                    : '',
            ],
            'client' => [
                'name' => (string)($client['name'] ?? 'Cliente'),
            ],
            'filters' => [
                'start_date' => $this->formatDate($startDate),
                'end_date' => $this->formatDate($endDate),
            ],
            'generated_at' => $generatedAt ?? Security::now()->format('d/m/Y H:i:s'),
            'rows' => $rows,
            'metrics' => [
                'total' => $total,
                'completed' => $completed,
                'overdue' => $overdue,
                'completion_rate' => $completionRate,
            ],
        ];
    }

    public function renderHtml(array $payload): string
    {
        $companyName = $this->esc((string)($payload['company']['name'] ?? 'TRAXTER'));
        $clientName = $this->esc((string)($payload['client']['name'] ?? 'Cliente'));
        $generatedAt = $this->esc((string)($payload['generated_at'] ?? Security::now()->format('d/m/Y H:i:s')));
        $periodStart = $this->esc((string)($payload['filters']['start_date'] ?? '-'));
        $periodEnd = $this->esc((string)($payload['filters']['end_date'] ?? '-'));
        $logoDataUri = $this->esc((string)($payload['company']['logo_data_uri'] ?? ''));
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
        $metrics = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];

        $metricsHtml = sprintf(
            '<div class="metrics">
                <div><strong>Total de tarefas:</strong> %d</div>
                <div><strong>Concluídas:</strong> %d</div>
                <div><strong>Atrasadas:</strong> %d</div>
                <div><strong>Taxa de conclusão:</strong> %s%%</div>
            </div>',
            (int)($metrics['total'] ?? 0),
            (int)($metrics['completed'] ?? 0),
            (int)($metrics['overdue'] ?? 0),
            number_format((float)($metrics['completion_rate'] ?? 0), 2, ',', '.')
        );

        $rowsHtml = '';
        foreach ($rows as $row) {
            $rowsHtml .= sprintf(
                '<tr style="background:%s;color:%s;">
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td><strong>%s</strong></td>
                    <td>%d%%</td>
                    <td>%s</td>
                </tr>',
                $this->esc((string)$row['status_bg']),
                $this->esc((string)$row['status_color']),
                $this->esc((string)$row['name']),
                $this->esc((string)$row['description']),
                $this->esc((string)$row['start_date']),
                $this->esc((string)$row['due_date']),
                $this->esc((string)$row['status_label']),
                (int)$row['progress'],
                $this->esc((string)$row['assignee'])
            );
        }

        $emptyHtml = '<div class="empty-state">Não há tarefas cadastradas para o cliente no período selecionado.</div>';
        $tableHtml = $rowsHtml === ''
            ? $emptyHtml
            : '<table>
                <thead>
                    <tr>
                        <th>Nome da tarefa</th>
                        <th>Descrição</th>
                        <th>Data de início</th>
                        <th>Previsão de conclusão</th>
                        <th>Status atual</th>
                        <th>Progresso</th>
                        <th>Responsável</th>
                    </tr>
                </thead>
                <tbody>' . $rowsHtml . '</tbody>
               </table>';

        $brandHtml = $logoDataUri !== ''
            ? '<img src="' . $logoDataUri . '" alt="Logo da empresa">'
            : '<div style="display:inline-block;padding:8px 12px;border-radius:8px;background:#111827;color:#ffffff;font-weight:700;">'
                . $companyName
                . '</div>';

        return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de tarefas do cliente</title>
    <style>
        @page { margin: 90px 28px 60px 28px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 11px; }
        .header { position: fixed; top: -70px; left: 0; right: 0; height: 62px; border-bottom: 1px solid #e5e7eb; }
        .header-wrap { width: 100%; }
        .brand { float: left; width: 40%; }
        .brand img { height: 34px; max-width: 170px; }
        .meta { float: right; width: 60%; text-align: right; font-size: 10px; color: #4b5563; }
        .meta strong { color: #111827; }
        .clearfix { clear: both; }
        .title { margin: 10px 0 4px; font-size: 16px; font-weight: bold; color: #111827; }
        .subtitle { margin: 0 0 8px; font-size: 11px; color: #4b5563; }
        .metrics { margin: 8px 0 12px; padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px; background: #f9fafb; }
        .metrics div { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; vertical-align: top; word-wrap: break-word; }
        th { background: #f3f4f6; color: #111827; font-size: 10px; text-transform: uppercase; }
        .empty-state { margin-top: 12px; border: 1px dashed #d1d5db; padding: 12px; background: #f9fafb; color: #4b5563; }
        .footer { position: fixed; bottom: -35px; left: 0; right: 0; height: 26px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 10px; text-align: right; padding-top: 8px; }
        .footer .page:after { content: counter(page) " / " counter(pages); }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-wrap">
            <div class="brand">' . $brandHtml . '</div>
            <div class="meta">
                <div><strong>Empresa:</strong> ' . $companyName . '</div>
                <div><strong>Cliente:</strong> ' . $clientName . '</div>
                <div><strong>Gerado em:</strong> ' . $generatedAt . '</div>
                <div><strong>Período:</strong> ' . $periodStart . ' a ' . $periodEnd . '</div>
            </div>
            <div class="clearfix"></div>
        </div>
    </div>

    <div class="footer">Página <span class="page"></span></div>

    <main>
        <div class="title">Relatório de tarefas do cliente</div>
        <div class="subtitle">Visão consolidada de status, progresso e responsabilidade das tarefas.</div>
        ' . $metricsHtml . '
        ' . $tableHtml . '
    </main>
</body>
</html>';
    }

    public function renderPdfBinaryFromHtml(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        return $dompdf->output();
    }

    private function normalizeStatusAndProgress(array $task, string $today): array
    {
        $status = (string)($task['status'] ?? 'todo');
        $dueDate = (string)($task['due_date'] ?? '');
        $isOverdue = $status !== 'done' && $dueDate !== '' && strlen($dueDate) >= 10 && substr($dueDate, 0, 10) < $today;

        if ($isOverdue) {
            return [
                'code' => 'overdue',
                'label' => 'Atrasada',
                'progress' => $status === 'doing' ? 70 : 35,
                'bg' => '#FEE2E2',
                'color' => '#991B1B',
            ];
        }

        return match ($status) {
            'done' => [
                'code' => 'done',
                'label' => 'Concluída',
                'progress' => 100,
                'bg' => '#DCFCE7',
                'color' => '#166534',
            ],
            'doing' => [
                'code' => 'doing',
                'label' => 'Em andamento',
                'progress' => 60,
                'bg' => '#FEF3C7',
                'color' => '#92400E',
            ],
            default => [
                'code' => 'pending',
                'label' => 'Pendente',
                'progress' => 20,
                'bg' => '#F3F4F6',
                'color' => '#374151',
            ],
        };
    }

    private function formatDate(string $value): string
    {
        $formatted = Security::formatDatabaseDate($value);
        return $formatted !== '' ? $formatted : '-';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function dompdfSupportsImages(): bool
    {
        return extension_loaded('gd');
    }
}
