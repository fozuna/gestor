<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BudgetProposalService;
use PHPUnit\Framework\TestCase;

final class BudgetProposalServiceTest extends TestCase
{
    public function testBuildSectionsContainsExpectedFinancialData(): void
    {
        $service = new BudgetProposalService();
        $sections = $service->buildSections(
            [
                'nome_proposta' => 'Site institucional',
                'descricao' => 'Desenvolvimento do portal e integrações.',
                'valor_total' => 12000.00,
                'valor_entrada' => 2000.00,
                'quantidade_parcelas' => 5,
                'valor_parcela' => 2000.00,
                'data_primeira_parcela' => '2026-05-10',
                'condicoes_pagamento' => 'PIX até o dia 10',
                'data_validade' => '2026-04-30',
            ],
            'Cliente XPTO',
            'TRAXTER',
            'Apresentação customizada'
        );

        self::assertSame('Site institucional', $sections['header']['proposta']);
        self::assertSame('R$ 12.000,00', $sections['investimento']);
        self::assertStringContainsString('Entrada de R$ 2.000,00', implode(' | ', $sections['condicoes']));
        self::assertStringContainsString('5 parcela(s)', implode(' | ', $sections['condicoes']));
        self::assertSame('30/04/2026', $sections['validade']);
    }

    public function testRenderTextIncludesCoreSections(): void
    {
        $service = new BudgetProposalService();
        $text = $service->renderText([
            'header' => ['cliente' => 'Cliente A', 'proposta' => 'Proposta A', 'gerado_em' => '01/01/2026 10:00'],
            'apresentacao' => 'Texto apresentação',
            'escopo' => 'Texto escopo',
            'investimento' => 'R$ 1.000,00',
            'condicoes' => ['Entrada', 'Parcelas'],
            'prazo' => 'Em 30 dias',
            'validade' => '31/12/2026',
        ]);

        self::assertStringContainsString('PROPOSTA COMERCIAL', $text);
        self::assertStringContainsString('Cliente: Cliente A', $text);
        self::assertStringContainsString('INVESTIMENTO', $text);
        self::assertStringContainsString('CONDIÇÕES DE PAGAMENTO', $text);
    }
}

