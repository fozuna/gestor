<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ReceiptChromiumPdfRenderer
{
    public function renderHtmlToPdf(string $html, string $outputPath): int
    {
        $tempHtml = tempnam(sys_get_temp_dir(), 'receipt-html-');
        if ($tempHtml === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário para o recibo.');
        }

        try {
            if (file_put_contents($tempHtml, $html) === false) {
                throw new RuntimeException('Não foi possível gravar HTML temporário do recibo.');
            }

            $dir = dirname($outputPath);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('Não foi possível criar diretório do recibo.');
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

