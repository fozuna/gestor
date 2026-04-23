<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\RuntimeConfigLoader;
use PHPUnit\Framework\TestCase;

final class RuntimeConfigLoaderTest extends TestCase
{
    public function testDetectEnvironmentPrefersExplicitAppEnv(): void
    {
        self::assertSame('local', RuntimeConfigLoader::detectEnvironment('local', 'gestor.traxter.com.br'));
        self::assertSame('production', RuntimeConfigLoader::detectEnvironment('production', 'localhost'));
    }

    public function testDetectEnvironmentFallsBackToHostname(): void
    {
        self::assertSame('local', RuntimeConfigLoader::detectEnvironment(null, 'localhost'));
        self::assertSame('local', RuntimeConfigLoader::detectEnvironment(null, 'crm.test'));
        self::assertSame('production', RuntimeConfigLoader::detectEnvironment(null, 'gestor.traxter.com.br'));
    }
}
