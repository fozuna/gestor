<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Installer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once dirname(__DIR__, 2) . '/bootstrap/runtime.php';

final class InstallerRuntimeConfigTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
            $this->originalEnv[$key] = getenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key]);
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }

        parent::tearDown();
    }

    public function testRuntimeConfigurationIsDetectedWithoutDependingOnEnvFileOnly(): void
    {
        putenv('DB_HOST=localhost');
        putenv('DB_PORT=3306');
        putenv('DB_DATABASE=crmtraxter');
        putenv('DB_USERNAME=shared_user');
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_PORT'] = '3306';
        $_ENV['DB_DATABASE'] = 'crmtraxter';
        $_ENV['DB_USERNAME'] = 'shared_user';

        $status = $this->invokeRuntimeConfigurationStatus();

        self::assertTrue($status['configured']);
        self::assertTrue($status['db_host']);
        self::assertTrue($status['db_port']);
        self::assertTrue($status['db_database']);
        self::assertTrue($status['db_username']);
    }

    public function testRuntimeConfigurationFailsWhenDatabaseIdentityIsIncomplete(): void
    {
        putenv('DB_HOST=localhost');
        putenv('DB_PORT=3306');
        putenv('DB_DATABASE');
        putenv('DB_USERNAME');
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_PORT'] = '3306';
        unset($_ENV['DB_DATABASE'], $_ENV['DB_USERNAME']);

        $status = $this->invokeRuntimeConfigurationStatus();

        self::assertFalse($status['configured']);
        self::assertFalse($status['db_database']);
        self::assertFalse($status['db_username']);
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeRuntimeConfigurationStatus(): array
    {
        $ref = new ReflectionClass(Installer::class);
        $method = $ref->getMethod('runtimeConfigurationStatus');
        $method->setAccessible(true);

        /** @var array<string, mixed> $result */
        $result = $method->invoke(null);
        return $result;
    }
}
