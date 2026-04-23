<?php
declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;
use Throwable;

final class ReceiptChromiumPdfRenderer
{
    public function renderHtmlToPdf(string $html, string $outputPath): int
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível criar diretório do recibo.');
        }

        $renderer = $this->preferredRenderer();
        if ($renderer === 'dompdf') {
            return $this->renderWithDompdf($html, $outputPath);
        }

        if ($renderer === 'chromium') {
            return $this->renderWithChromium($html, $outputPath);
        }

        try {
            return $this->renderWithChromium($html, $outputPath);
        } catch (Throwable $e) {
            error_log('[ReceiptChromiumPdfRenderer] Fallback para Dompdf: ' . $e->getMessage());
            return $this->renderWithDompdf($html, $outputPath);
        }
    }

    private function renderWithChromium(string $html, string $outputPath): int
    {
        $tempHtml = tempnam(sys_get_temp_dir(), 'receipt-html-');
        if ($tempHtml === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário para o recibo.');
        }

        try {
            if (file_put_contents($tempHtml, $html) === false) {
                throw new RuntimeException('Não foi possível gravar HTML temporário do recibo.');
            }

            $browserPath = $this->detectBrowserPath();
            $scriptPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'render-receipt-pdf.mjs';
            if (!is_file($scriptPath)) {
                throw new RuntimeException('Script de renderização do recibo não encontrado.');
            }

            $command = implode(' ', [
                'node',
                escapeshellarg($scriptPath),
                escapeshellarg($tempHtml),
                escapeshellarg($outputPath),
                escapeshellarg($browserPath),
            ]);

            $descriptors = [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2));
            if (!is_resource($process)) {
                throw new RuntimeException('Não foi possível iniciar o renderer Chromium do recibo.');
            }

            $stdout = stream_get_contents($pipes[1]) ?: '';
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[2]);

            $exitCode = proc_close($process);
            if ($exitCode !== 0) {
                throw new RuntimeException(
                    'Falha ao renderizar recibo com Chromium. ' . trim($stderr !== '' ? $stderr : $stdout)
                );
            }

            if (!is_file($outputPath)) {
                throw new RuntimeException('Renderer Chromium finalizou sem gerar o PDF do recibo.');
            }

            return (int)filesize($outputPath);
        } finally {
            if (is_file($tempHtml)) {
                @unlink($tempHtml);
            }
        }
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

    private function preferredRenderer(): string
    {
        $value = strtolower(trim((string)(getenv('RECEIPT_PDF_RENDERER') ?: 'auto')));
        return match ($value) {
            'chromium' => 'chromium',
            'dompdf' => 'dompdf',
            default => 'auto',
        };
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

    public function detectBrowserPath(): string
    {
        $envPath = getenv('CHROME_PATH');
        if (is_string($envPath) && $envPath !== '' && is_file($envPath)) {
            return $envPath;
        }

        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Nenhum binário compatível de Chrome/Edge foi encontrado para renderizar o recibo.');
    }
}
