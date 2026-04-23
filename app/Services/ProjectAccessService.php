<?php
declare(strict_types=1);

namespace App\Services;

final class ProjectAccessService
{
    public function canView(string $role): bool
    {
        return in_array($role, ['admin', 'gestor', 'user'], true);
    }

    public function canManage(string $role): bool
    {
        return in_array($role, ['admin', 'gestor'], true);
    }
}
