<?php
declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class ReceiptChromiumPdfRenderer
{
    public function renderHtmlToPdf(string $html, string $outputPath): int
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar diretório do recibo.');
        }

        return $this->renderWithDompdf($html, $outputPath);
    }

    private function renderWithDompdf(string $html, string $outputPath): int
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper([0, 0, 226.77, $this->estimateReceiptHeightPoints($html)]);
        $dompdf->render();

        $pdf = $dompdf->output();
        if (file_put_contents($outputPath, $pdf) === false) {
            throw new RuntimeException('Não foi possível gravar o PDF do recibo.');
        }

        return (int)filesize($outputPath);
    }

    private function estimateReceiptHeightPoints(string $html): float
    {
        $plain = preg_replace('/\s+/', ' ', strip_tags($html));
        $plain = is_string($plain) ? trim($plain) : '';
        if ($plain === '') {
            return 280.0;
        }

        $chars = mb_strlen($plain, 'UTF-8');
        $estimatedLines = (int)max(30, ceil($chars / 28));
        $height = 130.0 + ($estimatedLines * 5.9);
        return max(240.0, min(980.0, $height));
    }
}
