<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Path;
use App\Helpers\Security;
use App\Repositories\FinanceRepository;
use RuntimeException;

final class PaymentReceiptService
{
    private const MAX_PDF_BYTES = 512000;

    public function __construct(
        private readonly FinanceRepository $finance = new FinanceRepository(),
        private readonly ReceiptChromiumPdfRenderer $renderer = new ReceiptChromiumPdfRenderer()
    ) {
    }

    public function generateForPayment(int $tenantId, int $paymentId): array
    {
        $context = $this->finance->findPaymentReceiptContext($tenantId, $paymentId);
        if (!$context) {
            throw new RuntimeException('Pagamento não encontrado.');
        }

        $receiptNumber = $this->buildReceiptNumber(
            (int)$context['client_id'],
            (int)$context['project_id'],
            $this->finance->nextGlobalReceiptSequence()
        );

        $html = $this->renderHtml($context, $receiptNumber);
        $relativePath = $this->buildStorageRelativePath($tenantId, $paymentId);
        $absolutePath = $this->projectRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $bytes = $this->renderer->renderHtmlToPdf($html, $absolutePath);

        if ($bytes > self::MAX_PDF_BYTES) {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
            throw new RuntimeException('PDF excedeu 500KB.');
        }

        return [
            'number' => $receiptNumber,
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'bytes' => $bytes,
        ];
    }

    public function renderHtml(array $ctx, string $receiptNumber, string $unusedLogoDataUri = ''): string
    {
        $tenantName = $this->esc((string)($ctx['tenant_name'] ?? 'TRAXTER'));
        $clientName = $this->esc((string)($ctx['client_name'] ?? 'Cliente'));
        $clientDoc = trim((string)($ctx['client_document'] ?? ''));
        $clientIdInfo = $clientDoc !== ''
            ? $this->esc($clientDoc)
            : $this->esc((string)($ctx['client_email'] ?? 'Nao informado'));
        $projectName = $this->esc((string)($ctx['project_name'] ?? 'Projeto'));
        $projectDescription = trim((string)($ctx['project_description'] ?? ''));
        $projectDescriptionHtml = $projectDescription !== '' ? nl2br($this->esc($projectDescription)) : '<em>Nao informado</em>';
        $invoiceDescription = trim((string)($ctx['invoice_description'] ?? ''));
        $invoiceDescriptionHtml = $invoiceDescription !== '' ? nl2br($this->esc($invoiceDescription)) : '<em>Nao informado</em>';
        $invoiceCode = trim((string)($ctx['invoice_code'] ?? ''));
        $installmentNumber = (int)($ctx['installment_number'] ?? 0);
        $dueDate = Security::formatDatabaseDate((string)($ctx['due_date'] ?? ''));
        $paidDate = Security::formatDatabaseDateTime((string)($ctx['paid_at'] ?? ''));
        $issuedAt = Security::now()->format('d/m/Y H:i:s');
        $amountPaidValue = (float)($ctx['amount_paid'] ?? 0);
        $amountPaid = 'R$ ' . number_format($amountPaidValue, 2, ',', '.');
        $note = trim((string)($ctx['note'] ?? ''));
        $noteHtml = $note !== '' ? nl2br($this->esc($note)) : 'Sem observacoes adicionais.';
        $paymentMethod = trim((string)($ctx['payment_method'] ?? ''));
        $paymentMethodLabel = $paymentMethod !== '' ? $this->esc($this->paymentMethodLabel($paymentMethod)) : 'Nao informado';
        $statement = $this->esc(sprintf(
            'Recebemos de %s a importancia de %s referente a %s.',
            (string)($ctx['client_name'] ?? 'Cliente'),
            $amountPaid,
            $invoiceDescription !== '' ? $invoiceDescription : ('projeto ' . (string)($ctx['project_name'] ?? ''))
        ));

        return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Recibo de Pagamento</title>
  <style>
    @page { size: 80mm auto; margin: 0; }
    html, body {
      margin: 0;
      padding: 0;
      width: 80mm;
      max-width: 80mm;
      font-family: Arial, Helvetica, sans-serif;
      color: #111827;
      background: #ffffff;
      font-size: 10px;
      line-height: 1.35;
    }
    body { padding: 4mm 4mm 5mm; }
    .receipt { width: 72mm; max-width: 72mm; margin: 0 auto; }
    .header { text-align: center; border-bottom: 1px dashed #d4d4d8; padding-bottom: 3mm; margin-bottom: 3mm; }
    .company { font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .subtitle { margin-top: 1mm; font-size: 10px; font-weight: 700; text-transform: uppercase; }
    .meta { margin-top: 1mm; font-size: 9px; color: #52525b; }
    .statement { margin-bottom: 3mm; border: 1px solid #e5e7eb; border-radius: 4px; padding: 2.5mm; background: #fafafa; }
    .block { margin-bottom: 2.5mm; border: 1px solid #e5e7eb; border-radius: 4px; padding: 2.5mm; background: #ffffff; }
    .label { margin-bottom: 1mm; font-size: 9px; font-weight: 700; text-transform: uppercase; color: #374151; }
    .line { margin-bottom: 1mm; word-break: break-word; }
    .line:last-child { margin-bottom: 0; }
    .total { margin: 3mm 0; border: 1px solid #86efac; border-radius: 4px; background: #ecfdf5; padding: 3mm; text-align: center; }
    .total-label { font-size: 9px; font-weight: 700; text-transform: uppercase; color: #166534; }
    .total-value { margin-top: 1mm; font-size: 18px; font-weight: 700; color: #166534; }
    .footer { margin-top: 3mm; border-top: 1px dashed #d4d4d8; padding-top: 2mm; font-size: 9px; text-align: center; color: #52525b; }
  </style>
</head>
<body>
  <div class="receipt">
    <div class="header">
      <div class="company">' . $tenantName . '</div>
      <div class="subtitle">Recibo de Pagamento</div>
      <div class="meta">Recibo #' . $this->esc($receiptNumber) . '</div>
      <div class="meta">Documento nao fiscal</div>
    </div>

    <div class="statement">' . $statement . '</div>

    <div class="block">
      <div class="label">Cliente</div>
      <div class="line"><strong>Nome:</strong> ' . $clientName . '</div>
      <div class="line"><strong>Documento:</strong> ' . $clientIdInfo . '</div>
    </div>

    <div class="block">
      <div class="label">Referencia</div>
      <div class="line"><strong>Projeto:</strong> ' . $projectName . '</div>
      <div class="line"><strong>Descricao:</strong> ' . $projectDescriptionHtml . '</div>
      <div class="line"><strong>Documento:</strong> ' . $this->esc($invoiceCode !== '' ? $invoiceCode : '-') . '</div>
      <div class="line"><strong>Parcela:</strong> ' . ($installmentNumber > 0 ? $installmentNumber : '-') . '</div>
      <div class="line"><strong>Referencia:</strong> ' . $invoiceDescriptionHtml . '</div>
    </div>

    <div class="block">
      <div class="label">Pagamento</div>
      <div class="line"><strong>Forma:</strong> ' . $paymentMethodLabel . '</div>
      <div class="line"><strong>Vencimento:</strong> ' . $this->esc($dueDate !== '' ? $dueDate : '-') . '</div>
      <div class="line"><strong>Data:</strong> ' . $this->esc($paidDate !== '' ? $paidDate : '-') . '</div>
    </div>

    <div class="total">
      <div class="total-label">Total Pago</div>
      <div class="total-value">' . $this->esc($amountPaid) . '</div>
    </div>

    <div class="block">
      <div class="label">Observacoes</div>
      <div class="line">' . $noteHtml . '</div>
    </div>

    <div class="footer">
      <div>Emitido em ' . $this->esc($issuedAt) . '</div>
      <div>Recibo valido como comprovante comercial de pagamento.</div>
    </div>
  </div>
</body>
</html>';
    }

    public function buildReceiptNumber(int $client, int $project, int $seq): string
    {
        $clientPart = $client % 1000;
        $projectPart = $project % 100;
        $sequencePart = $seq % 1000;
        if ($sequencePart === 0) {
            $sequencePart = 1000;
        }
        return sprintf('%03d%02d%03d', $clientPart, $projectPart, $sequencePart);
    }

    public function buildDownloadFilename(string $receiptNumber): string
    {
        return 'recibo_' . date('Ymd_His') . '_' . $receiptNumber . '.pdf';
    }

    /**
     * @return array{0:int,1:int,2:float,3:float}
     */
    public function receiptPaperSizePoints(string $html = ''): array
    {
        $width = 226.77; // 80mm
        $height = $this->estimateReceiptHeightPoints($html);
        return [0, 0, $width, $height];
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function buildStorageRelativePath(int $tenantId, int $paymentId): string
    {
        return sprintf(
            'storage/recibos/%s/tenant-%d/recibo-%d.pdf',
            Security::now()->format('Ymd'),
            $tenantId,
            $paymentId
        );
    }

    private function projectRoot(): string
    {
        return Path::base();
    }

    private function paymentMethodLabel(string $method): string
    {
        return match (mb_strtolower(trim($method), 'UTF-8')) {
            'pix' => 'PIX',
            'boleto' => 'Boleto',
            'transferencia' => 'Transferencia',
            'cartao' => 'Cartao',
            'dinheiro' => 'Dinheiro',
            default => ucfirst(trim($method)),
        };
    }

    private function estimateReceiptHeightPoints(string $html): float
    {
        if (trim($html) === '') {
            return 260.0;
        }

        $plain = preg_replace('/\s+/', ' ', strip_tags($html));
        $plain = is_string($plain) ? trim($plain) : '';
        $chars = mb_strlen($plain, 'UTF-8');
        $estimatedLines = (int)max(28, ceil($chars / 30));
        $height = 120.0 + ($estimatedLines * 5.7);
        return max(220.0, min(900.0, $height));
    }
}
