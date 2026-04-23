<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PaymentReceiptService;
use PHPUnit\Framework\TestCase;

final class PaymentReceiptServiceTest extends TestCase
{
    public function testBuildReceiptNumberUsesClientProjectAndGlobalSequenceFormat(): void
    {
        $service = new PaymentReceiptService();
        self::assertSame('00105001', $service->buildReceiptNumber(1, 5, 1));
        self::assertSame('00105002', $service->buildReceiptNumber(1, 5, 2));
        self::assertSame('12399009', $service->buildReceiptNumber(123, 99, 9));
    }

    public function testRenderHtmlUsesReceiptCupomWidthAndRequiredSections(): void
    {
        $service = new PaymentReceiptService();
        $html = $service->renderHtml([
            'tenant_name' => 'TRAXTER',
            'client_name' => 'Cliente X',
            'client_document' => '123.456.789-00',
            'client_email' => 'cliente@example.com',
            'project_name' => 'Projeto Y',
            'project_description' => 'Desc',
            'invoice_code' => 'FAT-001',
            'invoice_description' => 'Parcela mensal',
            'installment_number' => 2,
            'due_date' => '2026-04-10',
            'paid_at' => '2026-04-10 12:00:00',
            'amount_paid' => 500.0,
            'payment_method' => 'pix',
            'note' => 'Pago integralmente',
        ], '00105001', '');

        self::assertStringContainsString('Recibo de Pagamento', $html);
        self::assertStringContainsString('Recibo #00105001', $html);
        self::assertStringContainsString('class="receipt"', $html);
        self::assertStringContainsString('@page {', $html);
        self::assertStringContainsString('size: 80mm auto;', $html);
        self::assertStringContainsString('width: 80mm;', $html);
        self::assertStringContainsString('max-width: 80mm;', $html);
        self::assertStringContainsString('Total Pago', $html);
        self::assertStringContainsString('Documento nao fiscal', $html);
        self::assertStringContainsString('Recebemos de Cliente X', $html);
        self::assertStringContainsString('Documento:</strong> FAT-001', $html);
        self::assertStringContainsString('PIX', $html);
    }

    public function testPaperWidthIsFixedToEightyMillimeters(): void
    {
        $service = new PaymentReceiptService();
        $paper = $service->receiptPaperSizePoints('<html><body><p>ok</p></body></html>');
        self::assertSame(226.77, $paper[2]);
        self::assertGreaterThanOrEqual(220.0, $paper[3]);
        self::assertLessThanOrEqual(900.0, $paper[3]);
    }

    public function testDownloadFilenameUsesExpectedPattern(): void
    {
        $service = new PaymentReceiptService();
        $filename = $service->buildDownloadFilename('00201001');
        self::assertMatchesRegularExpression('/^recibo_\d{8}_\d{6}_00201001\.pdf$/', $filename);
    }

    public function testRenderHtmlFormatsTransferPaymentAndFallbackDocument(): void
    {
        $service = new PaymentReceiptService();
        $html = $service->renderHtml([
            'tenant_name' => 'TRAXTER',
            'client_name' => 'Cliente Y',
            'client_document' => '',
            'client_email' => 'cliente@example.com',
            'project_name' => 'Projeto Z',
            'project_description' => '',
            'invoice_description' => 'Servico',
            'installment_number' => 1,
            'due_date' => '2026-04-10',
            'paid_at' => '2026-04-10 12:00:00',
            'amount_paid' => 1400.50,
            'payment_method' => 'transferencia',
            'note' => '',
        ], '00105002', '');

        self::assertStringContainsString('Transferencia', $html);
        self::assertStringContainsString('cliente@example.com', $html);
        self::assertStringContainsString('Sem observacoes adicionais.', $html);
    }
}
