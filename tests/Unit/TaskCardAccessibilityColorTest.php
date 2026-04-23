<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TaskCardAccessibilityColorTest extends TestCase
{
    public function testTaskCardCssContainsDoneAndPendingVisualStates(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/resources/css/app.css');
        self::assertIsString($css);
        self::assertStringContainsString('.task-card--done', $css);
        self::assertStringContainsString('.task-card--pending', $css);
        self::assertStringContainsString('background-color: #90EE90', $css);
        self::assertStringContainsString('background-color: #FFFFFF', $css);
        self::assertStringContainsString('border-left-style: solid', $css);
        self::assertStringContainsString('border-left-style: dashed', $css);
    }

    public function testContrastIsReadableAndStatesAreDistinguishable(): void
    {
        $text = '#111827';
        $doneBg = '#90EE90';
        $pendingBg = '#FFFFFF';

        $doneContrast = $this->contrastRatio($text, $doneBg);
        $pendingContrast = $this->contrastRatio($text, $pendingBg);
        $betweenStates = $this->contrastRatio($doneBg, $pendingBg);

        self::assertGreaterThanOrEqual(4.5, $doneContrast, 'Contraste insuficiente no card concluído.');
        self::assertGreaterThanOrEqual(4.5, $pendingContrast, 'Contraste insuficiente no card pendente.');
        self::assertGreaterThanOrEqual(1.3, $betweenStates, 'Cores de estado pouco distinguíveis.');
    }

    private function contrastRatio(string $hexA, string $hexB): float
    {
        $lumA = $this->relativeLuminance($hexA);
        $lumB = $this->relativeLuminance($hexB);
        $lighter = max($lumA, $lumB);
        $darker = min($lumA, $lumB);
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        $toLinear = static function (int $v): float {
            $c = $v / 255;
            return $c <= 0.03928 ? ($c / 12.92) : ((($c + 0.055) / 1.055) ** 2.4);
        };
        $rl = $toLinear($r);
        $gl = $toLinear($g);
        $bl = $toLinear($b);
        return (0.2126 * $rl) + (0.7152 * $gl) + (0.0722 * $bl);
    }

    /**
     * @return array{int,int,int}
     */
    private function hexToRgb(string $hex): array
    {
        $value = ltrim($hex, '#');
        if (strlen($value) !== 6) {
            throw new \InvalidArgumentException('Hex inválido: ' . $hex);
        }
        return [
            hexdec(substr($value, 0, 2)),
            hexdec(substr($value, 2, 2)),
            hexdec(substr($value, 4, 2)),
        ];
    }
}
