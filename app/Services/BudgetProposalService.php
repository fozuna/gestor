<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Security;
use Dompdf\Dompdf;
use Dompdf\Options;

final class BudgetProposalService
{
    public function buildSections(array $budget, string $clientName, string $tenantName, string $presentationText): array
    {
        $entry = (float)($budget['valor_entrada'] ?? 0);
        $total = (float)($budget['valor_total'] ?? 0);
        $count = (int)($budget['quantidade_parcelas'] ?? 0);
        $installment = (float)($budget['valor_parcela'] ?? 0);
        $firstDate = Security::formatDate((string)($budget['data_primeira_parcela'] ?? ''));
        $validity = Security::formatDate((string)($budget['data_validade'] ?? ''));
        $description = trim((string)($budget['descricao'] ?? ''));

        $paymentTerms = [];
        if ($entry > 0) {
            $paymentTerms[] = 'Entrada de R$ ' . number_format($entry, 2, ',', '.');
        }
        if ($count > 0) {
            $paymentTerms[] = sprintf(
                '%d parcela(s) de R$ %s%s',
                $count,
                number_format($installment, 2, ',', '.'),
                $firstDate !== '' ? ' a partir de ' . $firstDate : ''
            );
        }
        if (trim((string)($budget['condicoes_pagamento'] ?? '')) !== '') {
            $paymentTerms[] = trim((string)$budget['condicoes_pagamento']);
        }
        if ($paymentTerms === []) {
            $paymentTerms[] = 'Condições comerciais a combinar.';
        }

        return [
            'header' => [
                'empresa' => $tenantName,
                'cliente' => $clientName,
                'proposta' => (string)($budget['nome_proposta'] ?? 'Proposta comercial'),
                'gerado_em' => Security::now()->format('d/m/Y H:i'),
            ],
            'apresentacao' => trim($presentationText) !== ''
                ? trim($presentationText)
                : 'Apresentamos esta proposta com foco em resultado de negócio, previsibilidade financeira e execução com qualidade.',
            'escopo' => $description !== '' ? $description : 'Escopo detalhado será executado conforme alinhamento com o cliente.',
            'investimento' => 'R$ ' . number_format($total, 2, ',', '.'),
            'condicoes' => $paymentTerms,
            'prazo' => $firstDate !== '' ? 'Primeira parcela prevista para ' . $firstDate : 'Prazo de execução definido no início do projeto.',
            'validade' => $validity !== '' ? $validity : 'Sem data de validade definida',
        ];
    }

    public function renderText(array $sections): string
    {
        $condicoes = implode(PHP_EOL . '- ', $sections['condicoes']);
        return <<<TXT
TRAXTER
PROPOSTA COMERCIAL

Cliente: {$sections['header']['cliente']}
Proposta: {$sections['header']['proposta']}
Data de geração: {$sections['header']['gerado_em']}

APRESENTAÇÃO
{$sections['apresentacao']}

ESCOPO
{$sections['escopo']}

INVESTIMENTO
{$sections['investimento']}

CONDIÇÕES DE PAGAMENTO
- {$condicoes}

PRAZO
{$sections['prazo']}

VALIDADE
{$sections['validade']}
TXT;
    }

    public function renderHtml(array $sections, string $logoDataUri): string
    {
        $condicoesList = '';
        foreach ($sections['condicoes'] as $item) {
            $condicoesList .= '<li>' . $this->esc((string)$item) . '</li>';
        }
        $logoHtml = $logoDataUri !== ''
            ? '<img src="' . $this->esc($logoDataUri) . '" alt="TRAXTER" style="height:34px;max-width:190px;" />'
            : '<div style="font-size:18px;font-weight:700;color:#111827;">TRAXTER</div>';

        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Proposta comercial</title>
<style>
body{font-family:DejaVu Sans,Arial,sans-serif;color:#1f2937;font-size:12px}
.header{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e5e7eb;padding-bottom:10px;margin-bottom:14px}
h1{font-size:20px;margin:0;color:#111827}
h2{font-size:13px;margin:12px 0 5px;color:#111827}
.box{border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#f9fafb}
ul{margin:6px 0 0 18px}
li{margin:2px 0}
</style></head><body>
<div class="header"><div>' . $logoHtml . '</div><div style="text-align:right;font-size:11px;color:#4b5563;">'
            . '<div><strong>Cliente:</strong> ' . $this->esc((string)$sections['header']['cliente']) . '</div>'
            . '<div><strong>Gerado em:</strong> ' . $this->esc((string)$sections['header']['gerado_em']) . '</div>'
            . '</div></div>
<h1>' . $this->esc((string)$sections['header']['proposta']) . '</h1>
<h2>Apresentação</h2><div class="box">' . $this->esc((string)$sections['apresentacao']) . '</div>
<h2>Escopo</h2><div class="box">' . nl2br($this->esc((string)$sections['escopo'])) . '</div>
<h2>Investimento</h2><div class="box"><strong>' . $this->esc((string)$sections['investimento']) . '</strong></div>
<h2>Condições de pagamento</h2><div class="box"><ul>' . $condicoesList . '</ul></div>
<h2>Prazo</h2><div class="box">' . $this->esc((string)$sections['prazo']) . '</div>
<h2>Validade</h2><div class="box">' . $this->esc((string)$sections['validade']) . '</div>
</body></html>';
    }

    public function renderPdfBinary(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
