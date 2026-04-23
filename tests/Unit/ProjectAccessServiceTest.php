<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProjectAccessService;
use PHPUnit\Framework\TestCase;

final class ProjectAccessServiceTest extends TestCase
{
    public function testAllowsExpectedWorkspaceRoles(): void
    {
        $service = new ProjectAccessService();

        self::assertTrue($service->canView('admin'));
        self::assertTrue($service->canView('gestor'));
        self::assertTrue($service->canView('user'));
    }

    public function testRejectsUnknownRoles(): void
    {
        self::assertFalse((new ProjectAccessService())->canView('guest'));
    }

    public function testUserCannotManageDeletion(): void
    {
        self::assertFalse((new ProjectAccessService())->canManage('user'));
    }
}
