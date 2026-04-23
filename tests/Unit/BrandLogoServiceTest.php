<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BrandLogoService;
use PHPUnit\Framework\TestCase;

final class BrandLogoServiceTest extends TestCase
{
    public function testSelectsClearLogoForDarkBackground(): void
    {
        $service = new BrandLogoService();
        self::assertSame('clara', $service->pickVariantByBackground('#111827'));
        self::assertSame('clara', $service->pickVariantByBackground('rgb(20, 20, 20)'));
    }

    public function testSelectsDarkLogoForLightBackgroundAndFallback(): void
    {
        $service = new BrandLogoService();
        self::assertSame('escura', $service->pickVariantByBackground('#ffffff'));
        self::assertSame('escura', $service->pickVariantByBackground('fundo-desconhecido'));
    }

    public function testWebLogoUsesAssetPathWithCacheVersion(): void
    {
        $service = new BrandLogoService();
        $result = $service->webLogoForBackground('#FFFFFF');
        self::assertSame('escura', $result['variant']);
        $src = (string)$result['src'];
        $isAssetPath = str_starts_with($src, '/assets/images/logo-escura.png?v=');
        $isFallbackSvg = str_starts_with($src, 'data:image/svg+xml;utf8,');
        self::assertTrue($isAssetPath || $isFallbackSvg);
    }

    public function testPdfLogoFallsBackWhenAssetsAreMissing(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'brand-logo-missing-' . uniqid('', true);
        mkdir($tmp);
        try {
            $service = new BrandLogoService($tmp);
            $dataUri = $service->pdfLogoDataUriForBackground('TRAXTER', '#FE5516');
            self::assertStringStartsWith('data:image/svg+xml;utf8,', $dataUri);
        } finally {
            @rmdir($tmp);
        }
    }
}
